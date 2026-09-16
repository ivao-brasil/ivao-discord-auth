<?php

namespace Tests\Feature;

use App\ConsentmentModel;
use App\Infrastructure\Services\RolesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IvaoFixtures;
use Tests\TestCase;

class AuditRoleRulesTest extends TestCase
{
    use IvaoFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::preventStrayRequests();
    }

    private function account(string $vid, string $discordId): ConsentmentModel
    {
        return ConsentmentModel::create([
            'userVid' => $vid, 'discordId' => $discordId, 'nickName' => 'Fulano',
            'roles' => '', 'division' => 'BR', 'status' => true,
        ]);
    }

    private function fakeIvao(array $positions): void
    {
        config(['services.discord.bot_token' => 'bot-token', 'services.discord.guild_id' => '348405205890498580']);

        Http::fake([
            'discord.com/api/v10/guilds/*/roles' => Http::response([
                ['id' => '100', 'name' => 'Operações Especiais'],
                ['id' => '200', 'name' => 'Eventos'],
                ['id' => '300', 'name' => 'Staff'],
            ]),
            'api.ivao.aero/v2/oauth/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
            'api.ivao.aero/v2/userStaffPositions*' => Http::response($this->ivaoStaffPositions($positions)),
            'api.ivao.aero/v2/staffPositions*' => Http::response(['pages' => 1, 'items' => [
                ['id' => '-SOC', 'name' => 'Special Operations Coordinator', 'departmentTeam' => ['id' => 'SO-DIV-COORD', 'department' => ['name' => 'Special Operations']]],
                ['id' => '-SOA1', 'name' => 'Special Operations Advisor 1', 'departmentTeam' => ['id' => 'SO-DIV-ADV', 'department' => ['name' => 'Special Operations']]],
                ['id' => '-SOA2', 'name' => 'Special Operations Advisor 2', 'departmentTeam' => ['id' => 'SO-DIV-ADV', 'department' => ['name' => 'Special Operations']]],
                ['id' => '-EC', 'name' => 'Events Coordinator', 'departmentTeam' => ['id' => 'EVENT-DIV-COORD', 'department' => ['name' => 'Events']]],
                ['id' => 'WD6', 'name' => 'Web Developer', 'departmentTeam' => ['id' => 'DEV-WEB', 'department' => ['name' => 'Development Operations']]],
            ]]),
        ]);
    }

    public function test_a_rule_without_a_name_is_shown_by_its_discord_roles()
    {
        $this->account('123456', '555');
        $this->saveRoleRules([['id' => 'r1', 'roles' => ['100', '300'], 'staff' => ['BR-SOC']]]);
        $this->fakeIvao([['userId' => 123456, 'id' => 'BR-SOA2', 'connectAs' => 'BR-SOA2', 'onTrial' => false]]);

        $this->artisan('discord:audit-rules')
            ->expectsOutputToContain('"Operações Especiais + Staff"')
            ->assertSuccessful();
    }

    public function test_it_reports_a_position_no_rule_covers()
    {
        $this->account('123456', '555');
        $this->saveRoleRules([['id' => 'r1', 'name' => 'Especiais', 'roles' => ['100'], 'staff' => ['BR-SOC']]]);
        $this->fakeIvao([['userId' => 123456, 'id' => 'BR-SOA2', 'connectAs' => 'BR-SOA2', 'onTrial' => false]]);

        $this->artisan('discord:audit-rules')
            ->expectsOutputToContain('match no rule')
            ->expectsOutputToContain('BR-SOA2    Special Operations Advisor 2           1 member(s) -> "Especiais"')
            ->assertSuccessful();

        $this->assertSame(['BR-SOC'], app(RolesService::class)->rules()->first()->getStaff()->all());
    }

    public function test_fix_adds_the_position_to_the_rule_of_the_same_team()
    {
        $this->account('123456', '555');
        $this->saveRoleRules([
            ['id' => 'r1', 'name' => 'Especiais', 'roles' => ['100'], 'staff' => ['BR-SOC', 'BR-SOA1']],
            ['id' => 'r2', 'name' => 'Eventos', 'roles' => ['200'], 'staff' => ['BR-EC']],
        ]);
        $this->fakeIvao([['userId' => 123456, 'id' => 'BR-SOA2', 'connectAs' => 'BR-SOA2', 'onTrial' => false]]);

        $this->artisan('discord:audit-rules', ['--fix' => true])->assertSuccessful();

        $rules = app(RolesService::class)->rules();
        $this->assertSame(['BR-SOC', 'BR-SOA1', 'BR-SOA2'], $rules->firstWhere(fn ($rule) => $rule->getId() === 'r1')->getStaff()->all());
        $this->assertSame(['BR-EC'], $rules->firstWhere(fn ($rule) => $rule->getId() === 'r2')->getStaff()->all());
    }

    public function test_a_coordinators_rule_never_receives_an_advisor_position()
    {
        $this->account('123456', '555');
        $this->saveRoleRules([['id' => 'r1', 'name' => 'Coordenação', 'roles' => ['100'], 'staff' => ['BR-SOC']]]);
        $this->fakeIvao([['userId' => 123456, 'id' => 'BR-SOA2', 'connectAs' => 'BR-SOA2', 'onTrial' => false]]);

        $this->artisan('discord:audit-rules', ['--fix' => true])
            ->expectsOutputToContain('(department only)')
            ->assertSuccessful();

        $this->assertSame(['BR-SOC'], app(RolesService::class)->rules()->first()->getStaff()->all());
    }

    public function test_it_ignores_positions_of_other_divisions()
    {
        $this->account('123456', '555');
        $this->saveRoleRules([['id' => 'r1', 'name' => 'Especiais', 'roles' => ['100'], 'staff' => ['BR-SOC']]]);
        $this->fakeIvao([
            ['userId' => 123456, 'id' => 'PL-SOC', 'connectAs' => 'PL-SOC', 'onTrial' => false],
            ['userId' => 123456, 'id' => 'WD6', 'connectAs' => 'WD6', 'onTrial' => false],
        ]);

        $this->artisan('discord:audit-rules', ['--fix' => true])
            ->expectsOutputToContain('Every position held by a linked member is covered')
            ->assertSuccessful();

        $this->assertSame(['BR-SOC'], app(RolesService::class)->rules()->first()->getStaff()->all());
    }

    public function test_it_adds_the_position_to_every_rule_of_its_team()
    {
        $this->account('123456', '555');
        $this->saveRoleRules([
            ['id' => 'r1', 'name' => 'Especiais', 'roles' => ['100'], 'staff' => ['BR-SOA1']],
            ['id' => 'r2', 'name' => 'Staff', 'roles' => ['300'], 'staff' => ['BR-SOA1', 'BR-EC']],
        ]);
        $this->fakeIvao([['userId' => 123456, 'id' => 'BR-SOA2', 'connectAs' => 'BR-SOA2', 'onTrial' => false]]);

        $this->artisan('discord:audit-rules', ['--fix' => true])->assertSuccessful();

        $rules = app(RolesService::class)->rules();
        $this->assertSame(['BR-SOA1', 'BR-SOA2'], $rules->firstWhere(fn ($rule) => $rule->getId() === 'r1')->getStaff()->all());
        $this->assertSame(['BR-SOA1', 'BR-EC', 'BR-SOA2'], $rules->firstWhere(fn ($rule) => $rule->getId() === 'r2')->getStaff()->all());
    }

    public function test_it_ignores_positions_of_members_who_are_not_linked()
    {
        $this->account('123456', '555');
        $this->saveRoleRules([['id' => 'r1', 'name' => 'Especiais', 'roles' => ['100'], 'staff' => ['BR-SOC']]]);
        $this->fakeIvao([['userId' => 999999, 'id' => 'BR-SOA2', 'connectAs' => 'BR-SOA2', 'onTrial' => false]]);

        $this->artisan('discord:audit-rules')
            ->expectsOutputToContain('Every position held by a linked member is covered')
            ->assertSuccessful();
    }
}
