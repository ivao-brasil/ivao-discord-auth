<?php

namespace Tests\Feature;

use App\Application\Sync\MemberSyncService;
use App\Application\Sync\SyncStatusStore;
use App\ConsentmentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IvaoFixtures;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use IvaoFixtures, RefreshDatabase;

    private const GUILD = '348405205890498580';
    private const BOT = '1059928897682161664';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discord.bot_token' => 'bot-token',
            'services.discord.guild_id' => self::GUILD,
            'services.discord.client_id' => self::BOT,
            'brauth.admin_vids' => ['111111'],
            'app.locale' => 'en',
        ]);

        Storage::fake('local');
        Http::preventStrayRequests();
    }

    private function asAdmin(): self
    {
        return $this->withSession(['IVAO_USER' => $this->ivaoUser(['id' => 111111, 'firstName' => 'Admin'])]);
    }

    private function fakeDiscord(array $overrides = []): void
    {
        Http::fake(array_merge([
            'discord.com/api/v10/guilds/'.self::GUILD.'/roles' => Http::response([
                ['id' => self::GUILD, 'name' => '@everyone', 'color' => 0, 'position' => 0, 'permissions' => '0', 'managed' => false],
                ['id' => '910', 'name' => 'Server Admin', 'color' => 0, 'position' => 53, 'permissions' => '8', 'managed' => false],
                ['id' => '900', 'name' => 'Web', 'color' => 4666061, 'position' => 51, 'permissions' => '0', 'managed' => false],
                ['id' => '950', 'name' => 'Auth Bot', 'color' => 0, 'position' => 50, 'permissions' => '0', 'managed' => true],
                ['id' => '901', 'name' => 'Membro', 'color' => 9741240, 'position' => 26, 'permissions' => '0', 'managed' => false],
            ]),
            'discord.com/api/v10/guilds/'.self::GUILD.'/members/'.self::BOT => Http::response(['roles' => ['950']]),
        ], $overrides));
    }

    private function account(array $overrides = []): ConsentmentModel
    {
        return ConsentmentModel::create($overrides + ['userVid' => '123456', 'discordId' => '555', 'nickName' => 'Fulano | BR-WM', 'roles' => '', 'division' => 'BR', 'status' => true]);
    }

    public function test_only_admin_vids_reach_the_admin()
    {
        $member = $this->withSession(['IVAO_USER' => $this->ivaoUser()]);

        $member->get('/admin')->assertRedirect(route('home'));
        $member->getJson('/api/admin/rules')->assertRedirect(route('home'));

        $this->asAdmin()->get('/admin')->assertOk()->assertSee('Admin · VID 111111');
    }

    public function test_guests_are_sent_to_the_login()
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->getJson('/api/admin/members')->assertRedirect(route('login'));
    }

    public function test_lists_discord_roles_with_assignment_restrictions()
    {
        $this->fakeDiscord();

        $roles = $this->asAdmin()->getJson('/api/admin/roles')->assertOk()->json();

        $this->assertSame(['910', '900', '950', '901'], array_column($roles, 'id'));
        $this->assertSame(['assignable' => false, 'reason' => 'administrator', 'aboveBot' => true], array_intersect_key($roles[0], array_flip(['assignable', 'reason', 'aboveBot'])));
        $this->assertSame(['assignable' => true, 'reason' => null, 'aboveBot' => true], array_intersect_key($roles[1], array_flip(['assignable', 'reason', 'aboveBot'])));
        $this->assertSame('managed', $roles[2]['reason']);
        $this->assertSame('#94A3B8', $roles[3]['color']);
        $this->assertFalse($roles[3]['aboveBot']);
    }

    public function test_reads_rules_saved_in_the_legacy_format()
    {
        Storage::disk('local')->put('roles', Crypt::encryptString(json_encode([
            ['hash' => 'a1', 'id' => ['900'], 'sulfix' => 'BR-WM:BR-AWM'],
        ])));

        $this->asAdmin()->getJson('/api/admin/rules')
            ->assertOk()
            ->assertJsonPath('0.id', 'a1')
            ->assertJsonPath('0.roles', ['900'])
            ->assertJsonPath('0.staff', ['BR-WM', 'BR-AWM']);
    }

    public function test_saves_rules_and_logs_the_admin()
    {
        $this->fakeDiscord();
        Log::spy();

        $rules = [[
            'id' => 'web', 'name' => 'Web', 'roles' => ['900'], 'staff' => ['BR-WM'], 'includeTrial' => false,
            'divisionMode' => 'in', 'divisions' => ['BR'], 'minAtcRating' => '5', 'minPilotRating' => '',
            'minHours' => 50, 'requiresGca' => false, 'requiresVaOwnership' => true,
        ]];

        $this->asAdmin()->putJson('/api/admin/rules', ['rules' => $rules])
            ->assertOk()
            ->assertJsonPath('0.minAtcRating', 5)
            ->assertJsonPath('0.minPilotRating', null);

        $this->asAdmin()->getJson('/api/admin/rules')
            ->assertJsonPath('0.name', 'Web')
            ->assertJsonPath('0.includeTrial', false)
            ->assertJsonPath('0.requiresVaOwnership', true);

        Log::shouldHaveReceived('notice')->withArgs(fn ($message, $context) => $context['event'] === 'roles.updated' && $context['admin'] === 111111);
    }

    public function test_refuses_rules_with_administrator_or_unknown_roles()
    {
        $this->fakeDiscord();

        foreach (['910', '950', '123'] as $roleId) {
            $this->asAdmin()
                ->putJson('/api/admin/rules', ['rules' => [['id' => 'x', 'name' => 'Admins', 'roles' => [$roleId]]]])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('rules');
        }

        $this->assertFalse(Storage::disk('local')->exists('roles'));
    }

    public function test_validates_rule_fields()
    {
        $this->asAdmin()
            ->putJson('/api/admin/rules', ['rules' => [['id' => 'x', 'name' => '', 'roles' => [], 'minAtcRating' => 42]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rules.0.name', 'rules.0.roles', 'rules.0.minAtcRating']);
    }

    public function test_searches_and_filters_linked_members()
    {
        $web = $this->account();
        $away = $this->account(['userVid' => '654321', 'discordId' => '556', 'nickName' => 'Ana - 654321']);
        $failed = $this->account(['userVid' => '777777', 'discordId' => '557', 'nickName' => 'Rafael | BR-AWM']);
        $this->account(['userVid' => '888888', 'discordId' => '558', 'nickName' => 'Old', 'status' => false]);

        app(SyncStatusStore::class)->replace([$web->id => 'ok', $away->id => 'away', $failed->id => 'failed']);

        $this->asAdmin()->getJson('/api/admin/members')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('members.0.vid', '777777')
            ->assertJsonPath('members.0.status', 'failed');

        $this->asAdmin()->getJson('/api/admin/members?q=6543')->assertJsonPath('total', 1)->assertJsonPath('members.0.nickname', 'Ana - 654321');
        $this->asAdmin()->getJson('/api/admin/members?q=556')->assertJsonPath('total', 1);
        $this->asAdmin()->getJson('/api/admin/members?q=awm')->assertJsonPath('total', 1);
        $this->asAdmin()->getJson('/api/admin/members?status=away')->assertJsonPath('members.0.id', $away->id)->assertJsonPath('total', 1);
        $this->asAdmin()->getJson('/api/admin/members?status=pending')->assertJsonPath('members.0.id', $failed->id)->assertJsonPath('total', 1);
    }

    public function test_shows_member_changes_without_applying_them()
    {
        $account = $this->account();
        $this->saveRoleRules([['id' => 'web', 'roles' => ['900'], 'staff' => ['BR-WM']], ['id' => 'members', 'roles' => ['901']]]);
        $this->fakeDiscord([
            'api.ivao.aero/v2/oauth/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
            'api.ivao.aero/v2/userStaffPositions*' => Http::response($this->ivaoStaffPositions()),
            'api.ivao.aero/v2/users/*' => Http::response($this->ivaoUser()),
            'discord.com/api/v10/guilds/'.self::GUILD.'/members/555' => Http::response(['roles' => ['900', '777'], 'nick' => 'Fulano']),
        ]);

        $this->asAdmin()->getJson("/api/admin/members/{$account->id}")
            ->assertOk()
            ->assertJsonPath('away', false)
            ->assertJsonPath('discord.nickname', 'Fulano')
            ->assertJsonPath('ivao.staff', ['BR-WM'])
            ->assertJsonPath('ivao.eligible', true)
            ->assertJsonPath('changes.add', ['Membro'])
            ->assertJsonPath('changes.remove', [])
            ->assertJsonPath('changes.nickname', 'Fulano | BR-WM');

        Http::assertNotSent(fn (Request $r) => in_array($r->method(), ['PUT', 'PATCH', 'DELETE']));
    }

    public function test_syncs_a_member_on_request()
    {
        $account = $this->account();
        $this->saveRoleRules([['id' => 'members', 'roles' => ['901']]]);
        $this->fakeDiscord([
            'api.ivao.aero/v2/oauth/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
            'api.ivao.aero/v2/userStaffPositions*' => Http::response($this->ivaoStaffPositions()),
            'api.ivao.aero/v2/users/*' => Http::response($this->ivaoUser()),
            'discord.com/api/v10/guilds/'.self::GUILD.'/members/555' => fn (Request $request) => $request->method() === 'GET'
                ? Http::response(['roles' => [], 'nick' => 'Fulano | BR-WM'])
                : Http::response(null, 204),
            'discord.com/api/v10/*' => Http::response(null, 204),
        ]);

        $this->asAdmin()->postJson("/api/admin/members/{$account->id}/sync")
            ->assertOk()
            ->assertJsonPath('added', ['Membro'])
            ->assertJsonPath('skipped', false);

        $this->assertSame('ok', app(SyncStatusStore::class)->get($account->id)['status']);
    }

    public function test_reports_unreachable_apis_when_showing_a_member()
    {
        $account = $this->account();
        $this->fakeDiscord([
            'discord.com/api/v10/guilds/'.self::GUILD.'/members/555' => Http::response(['message' => 'Service Unavailable'], 503),
        ]);

        $this->asAdmin()->getJson("/api/admin/members/{$account->id}")
            ->assertStatus(503)
            ->assertJsonPath('message', __('admin.errors.unreachable'));
    }

    public function test_removes_a_member_from_the_server()
    {
        $account = $this->account();
        Http::fake(['discord.com/api/v10/*' => Http::response(null, 204)]);
        Log::spy();

        $this->asAdmin()->deleteJson("/api/admin/members/{$account->id}")->assertOk();

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === 'https://discord.com/api/v10/guilds/'.self::GUILD.'/members/555');
        $this->assertEquals(0, $account->refresh()->status);
        Log::shouldHaveReceived('notice')->withArgs(fn ($message, $context) => $context['event'] === 'admin.member.removed' && $context['vid'] === '123456');
    }
    public function test_admin_can_schedule_a_sync_of_every_member()
    {
        $this->asAdmin()->postJson('/api/admin/sync')->assertOk()->assertJson(['queued' => true]);

        $this->assertTrue(app(MemberSyncService::class)->fullRunWasRequested());
    }

    public function test_members_cannot_schedule_a_sync_of_every_member()
    {
        $this->withSession(['IVAO_USER' => $this->ivaoUser()])->postJson('/api/admin/sync')->assertRedirect(route('home'));

        $this->assertFalse(app(MemberSyncService::class)->fullRunWasRequested());
    }

    public function test_admin_sets_the_name_and_positions_of_a_member_by_hand()
    {
        $account = ConsentmentModel::create([
            'userVid' => '123456', 'discordId' => '555', 'nickName' => '| BR-WM',
            'roles' => '', 'division' => 'BR', 'status' => true,
        ]);

        $this->asAdmin()
            ->putJson("/api/admin/members/{$account->id}", ['firstName' => ' Joelson ', 'staffPositions' => 'br-wm, ivao-wd6'])
            ->assertOk()
            ->assertJson(['firstName' => 'Joelson', 'staffPositions' => 'BR-WM:IVAO-WD6']);

        $account->refresh();
        $this->assertSame('Joelson', $account->firstName);
        $this->assertSame('BR-WM:IVAO-WD6', $account->staffPositions);
    }

    public function test_clearing_the_fields_removes_what_was_kept()
    {
        $account = ConsentmentModel::create([
            'userVid' => '123456', 'discordId' => '555', 'nickName' => 'x', 'firstName' => 'Joelson',
            'staffPositions' => 'BR-WM', 'roles' => '', 'division' => 'BR', 'status' => true,
        ]);

        $this->asAdmin()->putJson("/api/admin/members/{$account->id}", ['firstName' => '', 'staffPositions' => ''])->assertOk();

        $account->refresh();
        $this->assertNull($account->firstName);
        $this->assertNull($account->staffPositions);
    }

    public function test_members_cannot_set_the_name_of_others()
    {
        $account = ConsentmentModel::create([
            'userVid' => '123456', 'discordId' => '555', 'nickName' => 'x',
            'roles' => '', 'division' => 'BR', 'status' => true,
        ]);

        $this->withSession(['IVAO_USER' => $this->ivaoUser()])
            ->putJson("/api/admin/members/{$account->id}", ['firstName' => 'Outro'])
            ->assertRedirect(route('home'));

        $this->assertNull($account->fresh()->firstName);
    }

}
