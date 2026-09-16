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
            'api.ivao.aero/v2/users/123456' => Http::response($this->ivaoUser()),
            'api.ivao.aero/v2/users/*' => Http::response($this->ivaoUser(['firstName' => null])),
            'discord.com/api/v10/guilds/'.self::GUILD.'/members/556' => Http::response(['nick' => 'Ciclano - 222222']),
            'discord.com/api/v10/guilds/'.self::GUILD.'/members/*' => Http::response(['nick' => null]),
            'discord.com/api/v10/guilds/'.self::GUILD.'/roles' => Http::response([]),
        ]);

        $this->artisan('discord:backfill-names')->assertSuccessful();

        $this->assertSame('Fulano', $public->fresh()->firstName);
        $this->assertSame('Ciclano', $onDiscord->fresh()->firstName);
        $this->assertSame('Beltrano', $fromDatabase->fresh()->firstName);
        $this->assertNull($nameless->fresh()->firstName);
    }

    public function test_dry_run_changes_nothing()
    {
        $account = $this->account('123456', '555', 'Fulano - 123456');

        Http::fake([
            'api.ivao.aero/v2/oauth/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
            'api.ivao.aero/v2/users/*' => Http::response($this->ivaoUser()),
            'discord.com/api/v10/*' => Http::response(['nick' => null]),
        ]);

        $this->artisan('discord:backfill-names --dry-run')->assertSuccessful();

        $this->assertNull($account->fresh()->firstName);
    }
}
