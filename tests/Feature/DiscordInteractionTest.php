<?php

namespace Tests\Feature;

use App\Application\Sync\MemberSyncService;
use App\ConsentmentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IvaoFixtures;
use Tests\TestCase;

class DiscordInteractionTest extends TestCase
{
    use IvaoFixtures, RefreshDatabase;

    private const GUILD = '348405205890498580';

    private string $secretKey;

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($keyPair);

        config([
            'services.discord.public_key' => bin2hex(sodium_crypto_sign_publickey($keyPair)),
            'services.discord.bot_token' => 'bot-token',
            'services.discord.guild_id' => self::GUILD,
            'services.discord.client_id' => 'app-id',
            'app.locale' => 'en',
        ]);

        Storage::fake('local');
        Http::preventStrayRequests();
        $this->withoutDefer();
    }

    private function interact(array $payload, ?string $signature = null)
    {
        $body = json_encode($payload);
        $timestamp = (string) time();

        return $this->call('POST', '/discord/interactions', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE_ED25519' => $signature ?? bin2hex(sodium_crypto_sign_detached($timestamp.$body, $this->secretKey)),
            'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
        ], $body);
    }

    private function syncCommand(string $discordId = '555'): array
    {
        return ['type' => 2, 'token' => 'interaction-token', 'data' => ['name' => 'sync'], 'member' => ['user' => ['id' => $discordId]]];
    }

    private function syncEveryoneCommand(string $discordId = '555'): array
    {
        return [
            'type' => 2,
            'token' => 'interaction-token',
            'data' => ['name' => 'sync', 'options' => [['name' => 'todos', 'type' => 5, 'value' => true]]],
            'member' => ['user' => ['id' => $discordId]],
        ];
    }

    public function test_answers_discord_ping()
    {
        $this->interact(['type' => 1])->assertOk()->assertExactJson(['type' => 1]);
    }

    public function test_rejects_requests_without_a_valid_signature()
    {
        $this->interact(['type' => 1], str_repeat('0', 128))->assertUnauthorized();
        $this->postJson('/discord/interactions', ['type' => 1])->assertUnauthorized();
    }

    public function test_unlinked_member_is_told_to_use_the_website()
    {
        $this->interact($this->syncCommand())
            ->assertOk()
            ->assertJsonPath('type', 4)
            ->assertJsonPath('data.flags', 64)
            ->assertJsonPath('data.content', __('text.syncNotLinked', ['url' => config('app.url')]));
    }

    public function test_sync_command_updates_the_member_and_edits_the_reply()
    {
        ConsentmentModel::create(['userVid' => '123456', 'discordId' => '555', 'nickName' => 'Fulano | BR-WM', 'roles' => '', 'division' => 'BR', 'status' => true]);
        $this->saveRoleRules([['id' => 'web', 'roles' => ['900'], 'staff' => ['BR-WM']]]);

        Http::fake([
            'api.ivao.aero/v2/oauth/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
            'api.ivao.aero/v2/users/*' => Http::response($this->ivaoUser()),
            'discord.com/api/v10/guilds/'.self::GUILD.'/roles' => Http::response([['id' => '900', 'name' => 'Web']]),
            'discord.com/api/v10/guilds/'.self::GUILD.'/members/555' => Http::response(['roles' => [], 'nick' => 'Fulano | BR-WM']),
            'discord.com/api/v10/*' => Http::response(null, 204),
        ]);

        $this->interact($this->syncCommand())
            ->assertOk()
            ->assertExactJson(['type' => 5, 'data' => ['flags' => 64]]);

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
            && $r->url() === 'https://discord.com/api/v10/webhooks/app-id/interaction-token/messages/@original'
            && str_contains($r['content'], __('text.syncRolesAdded', ['roles' => 'Web'])));
    }

    public function test_admin_can_ask_for_a_sync_of_every_member()
    {
        config(['brauth.admin_vids' => ['123456']]);
        ConsentmentModel::create(['userVid' => '123456', 'discordId' => '555', 'nickName' => 'x', 'roles' => '', 'division' => 'BR', 'status' => true]);

        $this->interact($this->syncEveryoneCommand())
            ->assertOk()
            ->assertJsonPath('type', 4)
            ->assertJsonPath('data.flags', 64)
            ->assertJsonPath('data.content', __('text.syncEveryoneQueued'));

        $this->assertTrue(app(MemberSyncService::class)->fullRunWasRequested());
        Http::assertNothingSent();
    }

    public function test_member_cannot_ask_for_a_sync_of_every_member()
    {
        config(['brauth.admin_vids' => ['111111']]);
        ConsentmentModel::create(['userVid' => '123456', 'discordId' => '555', 'nickName' => 'x', 'roles' => '', 'division' => 'BR', 'status' => true]);

        $this->interact($this->syncEveryoneCommand())
            ->assertOk()
            ->assertJsonPath('data.content', __('text.syncEveryoneNotAllowed'));

        $this->assertFalse(app(MemberSyncService::class)->fullRunWasRequested());
        Http::assertNothingSent();
    }

    public function test_sync_command_has_a_cooldown()
    {
        ConsentmentModel::create(['userVid' => '123456', 'discordId' => '555', 'nickName' => 'x', 'roles' => '', 'division' => 'BR', 'status' => true]);
        Http::fake([
            'api.ivao.aero/*' => Http::response(['error' => 'unavailable'], 503),
            'discord.com/api/v10/*' => Http::response(['roles' => [], 'nick' => null]),
        ]);

        $this->interact($this->syncCommand())->assertJsonPath('type', 5);

        $this->interact($this->syncCommand())
            ->assertJsonPath('type', 4)
            ->assertJsonPath('data.content', __('text.syncCooldown', ['minutes' => 5]));

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r['content'] === __('text.syncFailed'));
    }
}
