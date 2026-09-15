<?php

namespace App\Infrastructure\Socialite;

use GuzzleHttp\RequestOptions;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

/**
 * IVAO SSO (OAuth2 / OpenID Connect).
 *
 * @see https://api.ivao.aero/.well-known/openid-configuration
 */
class IVAOProvider extends AbstractProvider
{
    public const AUTHORIZE_URL = 'https://sso.ivao.aero/authorize';

    public const TOKEN_URL = 'https://api.ivao.aero/v2/oauth/token';

    public const USER_URL = 'https://api.ivao.aero/v2/users/me';

    protected $scopes = ['profile', 'configuration'];

    protected $scopeSeparator = ' ';

    protected $usesPKCE = true;

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(self::AUTHORIZE_URL, $state);
    }

    protected function getTokenUrl()
    {
        return self::TOKEN_URL;
    }

    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get(self::USER_URL, [
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    protected function mapUserToObject(array $user)
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['id'],
            'nickname' => $user['publicNickname'] ?? null,
            'name' => trim(($user['firstName'] ?? '').' '.($user['lastName'] ?? '')),
            'email' => $user['email'] ?? null,
            'avatar' => null,
        ]);
    }
}
