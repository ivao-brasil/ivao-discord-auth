<?php

namespace Tests\Feature;

use App\Application\Sync\MemberSyncService;
use App\Application\Sync\SyncResult;
use App\ConsentmentModel;
use App\Domain\Entities\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IvaoFixtures;
use Tests\TestCase;

class MemberSyncTest extends TestCase
{
    use IvaoFixtures, RefreshDatabase;

    private const GUILD = '348405205890498580';
    private const MEMBER_URL = 'https://discord.com/api/v10/guilds/'.self::GUILD.'/members/555';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discord.bot_token' => 'bot-token',
            'services.discord.guild_id' => self::GUILD,
            'brauth.sync.delay_ms' => 0,
        ]);

        Storage::fake('local');
        Http::preventStrayRequests();

        $this->saveRoleRules([
            ['id' => 'web', 'roles' => ['900'], 'staff' => ['BR-WM']],
            ['id' => 'members', 'roles' => ['901']],
        ]);
    }

    private function account(): ConsentmentModel
    {
        return ConsentmentModel::create(['userVid' => '123456', 'discordId' => '555', 'nickName' => 'Fulano | BR-WM', 'roles' => '', 'division' => 'BR', 'status' => true]);
    }

    private function fakeApis($ivaoUser, array $discordMember, array $overrides = []): void
    {
        Http::fake($overrides + [
            'api.ivao.aero/v2/oauth/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
            'api.ivao.aero/v2/users/*' => $ivaoUser,
            'discord.com/api/v10/guilds/'.self::GUILD.'/roles' => Http::response([
                ['id' => '900', 'name' => 'Web'], ['id' => '901', 'name' => 'Membro'], ['id' => '777', 'name' => 'Eventos'],
            ]),
            self::MEMBER_URL => fn (Request $request) => $request->method() === 'GET'
                ? Http::response($discordMember)
                : Http::response(null, 204),
            'discord.com/api/v10/*' => Http::response(null, 204),
        ]);
    }

    public function test_adds_missing_roles_removes_lost_ones_and_keeps_unmanaged_roles()
    {
        $account = $this->account();
        $this->fakeApis(
            Http::response($this->ivaoUser(['userStaffPositions' => [['id' => 'BR-AOC', 'connectAs' => 'BR-AOC']]])),
            ['roles' => ['900', '777'], 'nick' => 'Fulano | BR-WM']
        );

        $result = app(MemberSyncService::class)->sync($account);

        $this->assertSame(['901'], $result->added);
        $this->assertSame(['900'], $result->removed);
        $this->assertSame('Fulano | BR-AOC', $result->nickname);

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r->url() === self::MEMBER_URL.'/roles/901');
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === self::MEMBER_URL.'/roles/900');
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/roles/777'));
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === 'https://api.ivao.aero/v2/users/123456'
            && $r->hasHeader('Authorization', 'Bearer app-token'));

        $account->refresh();
        $this->assertSame('Fulano | BR-AOC', $account->nickName);
        $this->assertSame('Membro', $account->roles);
    }

    public function test_up_to_date_member_is_not_changed()
    {
        $account = $this->account();
        $this->fakeApis(Http::response($this->ivaoUser()), ['roles' => ['900', '901'], 'nick' => 'Fulano | BR-WM']);

        $result = app(MemberSyncService::class)->sync($account);

        $this->assertFalse($result->hasChanges());
        Http::assertNotSent(fn (Request $r) => in_array($r->method(), ['PUT', 'PATCH', 'DELETE']));
    }

    public function test_inactive_account_loses_managed_roles_but_keeps_nickname()
    {
        $account = $this->account();
        $this->fakeApis(
            Http::response($this->ivaoUser(['rating' => ['networkRating' => ['id' => Member::STATUS_INACTIVE]]])),
            ['roles' => ['900', '901', '777'], 'nick' => 'Apelido antigo']
        );

        $result = app(MemberSyncService::class)->sync($account);

        $this->assertEqualsCanonicalizing(['900', '901'], $result->removed);
        $this->assertNull($result->nickname);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH');
    }

    public function test_stored_name_is_used_when_ivao_hides_it()
    {
        $account = $this->account();
        $account->update(['firstName' => 'Fulano']);
        $this->fakeApis(
            Http::response($this->ivaoUser(['firstName' => null, 'userStaffPositions' => [['id' => 'BR-WM', 'connectAs' => 'BR-WM', 'onTrial' => false]]])),
            ['roles' => ['900'], 'nick' => 'Fulano - 123456']
        );

        $result = app(MemberSyncService::class)->sync($account);

        $this->assertSame('Fulano | BR-WM', $result->nickname);
    }

    public function test_the_name_from_ivao_is_kept_for_later_runs()
    {
        $account = $this->account();
        $this->fakeApis(Http::response($this->ivaoUser()), ['roles' => ['900', '901'], 'nick' => 'Fulano | BR-WM']);

        app(MemberSyncService::class)->sync($account);

        $this->assertSame('Fulano da Silva', $account->fresh()->firstName);
    }

    public function test_member_without_a_public_name_keeps_the_nickname()
    {
        $account = $this->account();
        $this->fakeApis(
            Http::response($this->ivaoUser(['firstName' => null, 'lastName' => null, 'userStaffPositions' => []])),
            ['roles' => ['901'], 'nick' => 'Fulano - 123456']
        );

        $result = app(MemberSyncService::class)->sync($account);

        $this->assertNull($result->nickname);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH');
    }

    public function test_deleted_ivao_account_loses_managed_roles()
    {
        $account = $this->account();
        $this->fakeApis(Http::response(['error' => 'not_found'], 404), ['roles' => ['900'], 'nick' => null]);

        $this->assertSame(['900'], app(MemberSyncService::class)->sync($account)->removed);
    }

    public function test_ivao_outage_changes_nothing()
    {
        $account = $this->account();
        $this->fakeApis(Http::response(['error' => 'unavailable'], 503), ['roles' => ['900'], 'nick' => null]);

        try {
            app(MemberSyncService::class)->sync($account);
            $this->fail('The sync should stop when IVAO cannot be reached.');
        } catch (RequestException) {
            Http::assertNotSent(fn (Request $r) => in_array($r->method(), ['PUT', 'PATCH', 'DELETE']));
        }
    }

    public function test_member_outside_the_server_is_reported_as_away()
    {
        $account = $this->account();
        $this->fakeApis(Http::response($this->ivaoUser()), [], [
            self::MEMBER_URL => Http::response(['message' => 'Unknown Member'], 404),
        ]);

        $this->assertSame(SyncResult::AWAY, app(MemberSyncService::class)->sync($account)->status);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'api.ivao.aero/v2/users'));
    }

    public function test_changes_refused_by_role_hierarchy_are_skipped()
    {
        $account = $this->account();
        $this->fakeApis(Http::response($this->ivaoUser()), ['roles' => [], 'nick' => null], [
            self::MEMBER_URL.'/roles/*' => Http::response(['message' => 'Missing Permissions', 'code' => 50013], 403),
            self::MEMBER_URL => fn (Request $request) => $request->method() === 'GET'
                ? Http::response(['roles' => [], 'nick' => null])
                : Http::response(['message' => 'Missing Permissions', 'code' => 50013], 403),
        ]);

        $result = app(MemberSyncService::class)->sync($account);

        $this->assertTrue($result->skipped);
        $this->assertFalse($result->hasChanges());
    }

    public function test_command_syncs_everyone_and_stores_a_summary()
    {
        $this->account();
        ConsentmentModel::create(['userVid' => '654321', 'discordId' => '556', 'nickName' => 'x', 'roles' => '', 'division' => 'BR', 'status' => true]);
        ConsentmentModel::create(['userVid' => '111111', 'discordId' => '557', 'nickName' => 'x', 'roles' => '', 'division' => 'BR', 'status' => false]);

        $this->fakeApis(Http::response($this->ivaoUser()), ['roles' => ['900', '901'], 'nick' => 'Fulano | BR-WM'], [
            'https://discord.com/api/v10/guilds/'.self::GUILD.'/members/556' => Http::response(['message' => 'Unknown Member'], 404),
        ]);

        $this->artisan('discord:sync')
            ->expectsOutputToContain('Checked 2, updated 0, away 1, failed 0.')
            ->assertSuccessful();

        $this->assertSame(2, Cache::get(MemberSyncService::LAST_RUN_CACHE_KEY)['checked']);
    }

    public function test_command_fails_for_a_vid_without_linked_account()
    {
        $this->artisan('discord:sync 999999')->assertFailed();
    }
}
