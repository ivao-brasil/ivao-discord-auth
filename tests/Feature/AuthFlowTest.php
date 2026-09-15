<?php

namespace Tests\Feature;

use App\ConsentmentModel;
use App\Domain\Entities\Member;
use App\Infrastructure\Services\RolesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private const GUILD = '348405205890498580';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ivao.client_id' => 'ivao-client',
            'services.ivao.client_secret' => 'ivao-secret',
            'services.discord.bot_token' => 'bot-token',
            'services.discord.guild_id' => self::GUILD,
            'brauth.admin_vids' => ['111111'],
            'brauth.title' => 'Test Title',
            'app.locale' => 'en',
        ]);

        Storage::fake('local');
        Http::preventStrayRequests();
    }

    private function ivaoUser(array $overrides = []): array
    {
        return array_merge([
            'id' => 123456,
            'firstName' => 'Fulano da Silva',
            'lastName' => 'Souza',
            'divisionId' => 'BR',
            'rating' => ['networkRating' => ['id' => Member::STATUS_ACTIVE]],
            'hours' => [['type' => 'pilot', 'hours' => 36000], ['type' => 'atc', 'hours' => 0]],
            'userStaffPositions' => [['id' => 'BR-WM']],
        ], $overrides);
    }

    private function mockSocialite(string $driver, SocialiteUser $user): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with($driver)->andReturn($provider);
    }

    private function saveRoleRules(array $rules): void
    {
        app(RolesService::class)->saveAllRoles($rules);
    }

    public function test_guest_is_redirected_to_ivao_login()
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_ivao_login_redirects_to_sso_with_pkce()
    {
        $location = $this->get('/ivao/login')->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://sso.ivao.aero/authorize?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('ivao-client', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('profile configuration', $query['scope']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertNotEmpty($query['state']);
        $this->assertStringEndsWith('/ivao/callback', $query['redirect_uri']);
    }

    public function test_ivao_callback_with_error_shows_message()
    {
        $this->get('/ivao/callback?error=access_denied')
            ->assertOk()
            ->assertSee(__('text.invalidTokenException'));
    }

    public function test_ivao_callback_stores_user_and_shows_index()
    {
        $this->mockSocialite('ivao', (new SocialiteUser)->setRaw($this->ivaoUser())->map(['id' => 123456]));

        $this->get('/ivao/callback?code=abc&state=xyz')->assertRedirect('/');

        $this->assertSame('123456', (string) session('IVAO_USER.id'));
        $this->assertSame([['id' => 'BR-WM']], session('IVAO_USER.userStaffPositions'));

        $this->get('/')->assertOk()->assertSee('Fulano da Silva')->assertSee('Test Title');
    }

    public function test_suspended_member_cannot_see_index()
    {
        $this->withSession(['IVAO_USER' => $this->ivaoUser(['rating' => ['networkRating' => ['id' => Member::STATUS_SUSPENDED]]])])
            ->get('/')
            ->assertOk()
            ->assertSee(__('text.accountSuspendedException'));
    }

    public function test_discord_callback_joins_guild_with_roles_and_nickname()
    {
        $this->saveRoleRules([
            ['hash' => 'a', 'id' => ['900'], 'sulfix' => 'BR-WM:BR-AWM'],
            ['hash' => 'b', 'id' => ['901'], 'sulfix' => 'Member'],
            ['hash' => 'c', 'id' => ['902'], 'sulfix' => 'BR-DIR'],
        ]);

        Http::fake([
            'discord.com/api/v10/guilds/'.self::GUILD.'/roles' => Http::response([
                ['id' => '900', 'name' => 'Webmaster'],
                ['id' => '901', 'name' => 'Membro'],
                ['id' => '902', 'name' => 'Director'],
            ]),
            'discord.com/api/v10/*' => Http::response(null, 204),
        ]);

        $this->mockSocialite('discord', (new SocialiteUser)->setToken('discord-user-token')->map(['id' => '555']));

        $this->withSession(['IVAO_USER' => $this->ivaoUser()])
            ->get('/discord/callback?code=abc&state=xyz')
            ->assertRedirect('/success');

        $base = 'https://discord.com/api/v10/guilds/'.self::GUILD.'/members/555';
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r->url() === $base
            && $r['access_token'] === 'discord-user-token'
            && $r->hasHeader('Authorization', 'Bot bot-token'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r->url() === "$base/roles/900");
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r->url() === "$base/roles/901");
        Http::assertNotSent(fn (Request $r) => $r->url() === "$base/roles/902");
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r->url() === $base && $r['nick'] === 'Fulano | BR-WM');

        $consentment = ConsentmentModel::sole();
        $this->assertSame('123456', $consentment->userVid);
        $this->assertSame('555', $consentment->discordId);
        $this->assertSame('Webmaster:Membro', $consentment->roles);
        $this->assertSame('BR', $consentment->division);
        $this->assertEquals(1, $consentment->status);
    }

    public function test_member_without_enough_hours_is_refused()
    {
        $this->saveRoleRules([['hash' => 'b', 'id' => ['901'], 'sulfix' => 'Member']]);
        Http::fake();
        $this->mockSocialite('discord', (new SocialiteUser)->setToken('t')->map(['id' => '555']));

        $this->withSession(['IVAO_USER' => $this->ivaoUser(['hours' => [['type' => 'pilot', 'hours' => 3600]]])])
            ->get('/discord/callback?code=abc&state=xyz')
            ->assertOk()
            ->assertSee(__('text.invalidPermissionException'));

        Http::assertNothingSent();
        $this->assertSame(0, ConsentmentModel::count());
    }

    public function test_discord_callback_requires_ivao_login()
    {
        $this->get('/discord/callback?code=abc')->assertRedirect(route('login'));
    }

    public function test_admin_pages_are_limited_to_admin_vids()
    {
        $this->withSession(['IVAO_USER' => $this->ivaoUser()])->get('/admin')->assertRedirect(route('home'));

        Http::fake(['discord.com/api/v10/guilds/*/roles' => Http::response([['id' => '900', 'name' => 'Webmaster']])]);

        $admin = ['IVAO_USER' => $this->ivaoUser(['id' => 111111])];
        $this->withSession($admin)->get('/admin')->assertOk();
        $this->withSession($admin)->getJson('/api/discord/roles')->assertExactJson([['id' => '900', 'name' => 'Webmaster']]);

        $rules = [['hash' => 'a', 'id' => ['900'], 'sulfix' => 'BR-WM']];
        $this->withSession($admin)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->postJson('/api/discord/saveRoles', $rules)->assertOk();
        $this->withSession($admin)->getJson('/api/discord/actualRoles')->assertExactJson($rules);

        Storage::disk('local')->assertExists('roles');
    }

    public function test_revoke_removes_linked_accounts_from_guild()
    {
        ConsentmentModel::create(['userVid' => '123456', 'discordId' => '555', 'nickName' => 'x', 'roles' => 'r', 'division' => 'BR', 'status' => true]);
        Http::fake(['discord.com/api/v10/*' => Http::response(null, 204)]);

        $this->withSession(['IVAO_USER' => $this->ivaoUser()])->get('/revoke')->assertRedirect('/');

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE'
            && $r->url() === 'https://discord.com/api/v10/guilds/'.self::GUILD.'/members/555');
        $this->assertEquals(0, ConsentmentModel::sole()->status);
    }

    public function test_revoke_ignores_accounts_that_already_left_the_guild()
    {
        ConsentmentModel::create(['userVid' => '123456', 'discordId' => '555', 'nickName' => 'x', 'roles' => 'r', 'division' => 'BR', 'status' => true]);
        Http::fake(['discord.com/api/v10/*' => Http::response(['message' => 'Unknown Member', 'code' => 10007], 404)]);

        $this->withSession(['IVAO_USER' => $this->ivaoUser()])->get('/revoke')->assertRedirect('/');

        $this->assertEquals(0, ConsentmentModel::sole()->status);
    }
}
