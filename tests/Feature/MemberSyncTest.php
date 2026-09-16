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
use Illuminate\Support\Facades\Log;
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

    public function test_inactive_account_keeps_its_roles()
    {
        $account = $this->account();
        $this->fakeApis(
            Http::response($this->ivaoUser(['rating' => ['networkRating' => ['id' => Member::STATUS_INACTIVE]]])),
            ['roles' => ['900', '901', '777'], 'nick' => 'Fulano | BR-WM']
        );

        $result = app(MemberSyncService::class)->sync($account);

        $this->assertSame([], $result->removed);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE');
    }

    public function test_suspended_account_loses_managed_roles_but_keeps_nickname()
    {
        $account = $this->account();
        $this->fakeApis(
            Http::response($this->ivaoUser(['rating' => ['networkRating' => ['id' => Member::STATUS_SUSPENDED]]])),
            ['roles' => ['900', '901', '777'], 'nick' => 'Apelido antigo']
        );

        $result = app(MemberSyncService::class)->sync($account);

        $this->assertEqualsCanonicalizing(['900', '901'], $result->removed);
        $this->assertNull($result->nickname);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH');
    }

    public function test_a_hidden_profile_keeps_its_staff_roles_and_nickname()
    {
        $account = $this->account();
        $account->update(['firstName' => 'Fulano', 'staffPositions' => 'BR-WM']);

        // IVAO hides the name and the staff positions of a private profile, so the
        // answer cannot be read as "this member is not staff anymore"
        $this->fakeApis(
            Http::response($this->ivaoUser(['firstName' => null, 'lastName' => null, 'userStaffPositions' => []])),
            ['roles' => ['900', '901'], 'nick' => 'Fulano | BR-WM']
        );

        $result = app(MemberSyncService::class)->sync($account);

        $this->assertSame([], $result->removed);
        $this->assertNull($result->nickname);
        Http::assertNotSent(fn (Request $r) => in_array($r->method(), ['DELETE', 'PATCH']));
    }

    public function test_a_hidden_profile_without_kept_positions_is_left_alone()
    {
        $account = $this->account();
        $account->update(['firstName' => 'Fulano', 'staffPositions' => null]);

        $this->fakeApis(
            Http::response($this->ivaoUser(['firstName' => null, 'lastName' => null, 'userStaffPositions' => []])),
            ['roles' => ['900', '901'], 'nick' => 'Fulano | BR-WM']
        );

        $result = app(MemberSyncService::class)->sync($account);

        $this->assertNull($result->nickname);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH');
    }

    public function test_a_hidden_profile_gets_the_nickname_of_the_name_and_positions_kept_from_the_login()
    {
        $account = $this->account();
        $account->update(['firstName' => 'Fulano', 'staffPositions' => 'BR-WM:IVAO-WD6']);

        $this->fakeApis(
            Http::response($this->ivaoUser(['firstName' => null, 'lastName' => null, 'userStaffPositions' => []])),
            ['roles' => ['900', '901'], 'nick' => '- 123456']
        );

        $result = app(MemberSyncService::class)->sync($account);

        $this->assertSame('Fulano | BR-WM IVAO-WD6', $result->nickname);
    }

    public function test_the_positions_from_ivao_are_kept_for_later_runs()
    {
        $account = $this->account();
        $this->fakeApis(Http::response($this->ivaoUser()), ['roles' => ['900', '901'], 'nick' => 'Fulano | BR-WM']);

        app(MemberSyncService::class)->sync($account);

        $this->assertSame('BR-WM', $account->fresh()->staffPositions);
    }

    public function test_a_hidden_profile_still_loses_roles_it_cannot_qualify_for()
    {
        $account = $this->account();
        $this->saveRoleRules([
            ['id' => 'web', 'roles' => ['900'], 'staff' => ['BR-WM']],
            ['id' => 'pilots', 'roles' => ['901'], 'minPilotRating' => 10],
        ]);

        $this->fakeApis(
            Http::response($this->ivaoUser(['firstName' => null, 'userStaffPositions' => []])),
            ['roles' => ['900', '901'], 'nick' => 'Fulano | BR-WM']
        );

        $result = app(MemberSyncService::class)->sync($account);

        // 900 comes from a staff rule and stays, 901 depends on the rating, which is visible
        $this->assertSame(['901'], $result->removed);
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

    public function test_a_run_that_removes_roles_from_too_many_members_stops()
    {
        config(['brauth.sync.max_removals' => 2]);

        foreach (range(1, 6) as $i) {
            ConsentmentModel::create([
                'userVid' => "12345{$i}", 'discordId' => '555', 'nickName' => 'x',
                'roles' => '', 'division' => 'BR', 'status' => true,
            ]);
        }

        // Everyone is suspended, so every member would lose the managed roles
        $this->fakeApis(
            Http::response($this->ivaoUser(['rating' => ['networkRating' => ['id' => Member::STATUS_SUSPENDED]]])),
            ['roles' => ['900', '901'], 'nick' => null]
        );

        Log::spy();

        $summary = app(MemberSyncService::class)->syncAll();

        $this->assertTrue($summary['aborted']);
        $this->assertLessThan(6, $summary['checked']);
        Log::shouldHaveReceived('critical')->once();
    }

    public function test_an_account_ivao_does_not_answer_for_keeps_everything()
    {
        $account = $this->account();
        $this->fakeApis(Http::response(['error' => 'not_found'], 404), ['roles' => ['900'], 'nick' => null]);

        $result = app(MemberSyncService::class)->sync($account);

        $this->assertSame(SyncResult::UNVERIFIED, $result->status);
        $this->assertSame([], $result->removed);
        Http::assertNotSent(fn (Request $r) => in_array($r->method(), ['PUT', 'DELETE', 'PATCH']));
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
    public function test_dry_run_lists_the_changes_without_touching_discord()
    {
        $this->account();
        $this->fakeApis(Http::response($this->ivaoUser()), ['roles' => ['777'], 'nick' => 'Apelido antigo']);

        $this->artisan('discord:sync --dry-run')
            ->expectsOutputToContain('Nothing was sent to Discord.')
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => in_array($r->method(), ['PUT', 'PATCH', 'DELETE']));
    }

}
