<?php

namespace Tests\Feature;

use App\ConsentmentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IvaoFixtures;
use Tests\TestCase;

class BackfillNamesTest extends TestCase
{
    use IvaoFixtures, RefreshDatabase;

    private const GUILD = '348405205890498580';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discord.bot_token' => 'bot-token',
            'services.discord.guild_id' => self::GUILD,
        ]);

        Storage::fake('local');
        Http::preventStrayRequests();
    }

    private function account(string $vid, string $discordId, string $nickName): ConsentmentModel
    {
        return ConsentmentModel::create([
            'userVid' => $vid, 'discordId' => $discordId, 'nickName' => $nickName,
            'roles' => '', 'division' => 'BR', 'status' => true,
        ]);
    }

    public function test_it_fills_the_name_from_ivao_the_discord_nickname_or_the_stored_one()
    {
        $public = $this->account('123456', '555', '- 123456');
        $onDiscord = $this->account('222222', '556', '- 222222');
        $fromDatabase = $this->account('333333', '557', 'Beltrano | BR-DIR');
        $nameless = $this->account('444444', '558', '- 444444');

        Http::fake([
            'api.ivao.aero/v2/oauth/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
            'api.ivao.aero/v2/userStaffPositions*' => Http::response($this->ivaoStaffPositions([
                ['userId' => 123456, 'id' => 'BR-WM', 'connectAs' => 'BR-WM', 'onTrial' => false],
                ['userId' => 333333, 'id' => 'BR-DIR', 'connectAs' => 'BR-DIR', 'onTrial' => false],
            ])),
            'api.ivao.aero/v2/users/123456' => Http::response($this->ivaoUser()),
            'api.ivao.aero/v2/users/*' => Http::response($this->ivaoUser(['firstName' => null])),
            'discord.com/api/v10/guilds/'.self::GUILD.'/audit-logs*' => Http::response(['audit_log_entries' => []]),
            'discord.com/api/v10/guilds/'.self::GUILD.'/members/556' => Http::response(['nick' => 'Ciclano - 222222']),
            'discord.com/api/v10/guilds/'.self::GUILD.'/members/*' => Http::response(['nick' => null]),
            'discord.com/api/v10/guilds/'.self::GUILD.'/roles' => Http::response([]),
        ]);

        $this->artisan('discord:backfill-names')->assertSuccessful();

        $this->assertSame('Fulano', $public->fresh()->firstName);
        $this->assertSame('BR-WM', $public->fresh()->staffPositions);
        $this->assertSame('Ciclano', $onDiscord->fresh()->firstName);
        $this->assertSame('Beltrano', $fromDatabase->fresh()->firstName);
        // Taken from the network list, not from the nickname, which can be years out of date
        $this->assertSame('BR-DIR', $fromDatabase->fresh()->staffPositions);
        $this->assertNull($nameless->fresh()->firstName);
    }

    public function test_it_takes_the_name_from_the_audit_log_when_nothing_else_has_it()
    {
        $staff = $this->account('444444', '558', '| BR-FOC');

        Http::fake([
            'api.ivao.aero/v2/oauth/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
            'api.ivao.aero/v2/userStaffPositions*' => Http::response($this->ivaoStaffPositions([
                ['userId' => 444444, 'id' => 'BR-FOC', 'connectAs' => 'BR-FOC', 'onTrial' => false],
            ])),
            'api.ivao.aero/v2/users/*' => Http::response($this->ivaoUser(['firstName' => null])),
            'discord.com/api/v10/guilds/'.self::GUILD.'/audit-logs*' => Http::sequence()
                ->push(['audit_log_entries' => [[
                    'id' => '2', 'target_id' => '558',
                    'changes' => [['key' => 'nick', 'old_value' => 'Pedro | BR-TA11', 'new_value' => '| BR-FOC']],
                ]]])
                ->push(['audit_log_entries' => []]),
            'discord.com/api/v10/guilds/'.self::GUILD.'/members/*' => Http::response(['nick' => '| BR-FOC']),
            'discord.com/api/v10/guilds/'.self::GUILD.'/roles' => Http::response([]),
        ]);

        $this->artisan('discord:backfill-names')->assertSuccessful();

        $this->assertSame('Pedro', $staff->fresh()->firstName);
        // The position comes from the network list, not from the nickname the audit log kept
        $this->assertSame('BR-FOC', $staff->fresh()->staffPositions);
    }

    public function test_no_audit_log_leaves_the_name_empty()
    {
        $staff = $this->account('444444', '558', '| BR-FOC');

        Http::fake([
            'api.ivao.aero/v2/oauth/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
            'api.ivao.aero/v2/userStaffPositions*' => Http::response($this->ivaoStaffPositions([])),
            'api.ivao.aero/v2/users/*' => Http::response($this->ivaoUser(['firstName' => null])),
            'discord.com/api/v10/guilds/'.self::GUILD.'/members/*' => Http::response(['nick' => '| BR-FOC']),
        ]);

        $this->artisan('discord:backfill-names --no-audit-log')->assertSuccessful();

        $this->assertNull($staff->fresh()->firstName);
    }

    public function test_dry_run_changes_nothing()
    {
        $account = $this->account('123456', '555', 'Fulano - 123456');

        Http::fake([
            'api.ivao.aero/v2/oauth/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
            'api.ivao.aero/v2/userStaffPositions*' => Http::response($this->ivaoStaffPositions([
                ['userId' => 123456, 'id' => 'BR-WM', 'connectAs' => 'BR-WM', 'onTrial' => false],
                ['userId' => 333333, 'id' => 'BR-DIR', 'connectAs' => 'BR-DIR', 'onTrial' => false],
            ])),
            'api.ivao.aero/v2/users/*' => Http::response($this->ivaoUser()),
            'discord.com/api/v10/guilds/'.self::GUILD.'/audit-logs*' => Http::response(['audit_log_entries' => []]),
            'discord.com/api/v10/*' => Http::response(['nick' => null]),
        ]);

        $this->artisan('discord:backfill-names --dry-run')->assertSuccessful();

        $this->assertNull($account->fresh()->firstName);
    }
}
