<?php


namespace App\Infrastructure\Http\Controllers;
use App\Application\Contracts\DiscordIVAOAuthServiceInterface;
use App\Domain\Contracts\IVAOApiServiceContract;
use App\Domain\Entities\Member;
use App\Exceptions\InvalidPermissionException;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class DiscordController extends Controller
{
    private $discordIVAOAuth;
    private $IVAOAPI;

    public function __construct(DiscordIVAOAuthServiceInterface $discordIVAOAuth, IVAOApiServiceContract $IVAOAPI)
    {
        $this->discordIVAOAuth = $discordIVAOAuth;
        $this->IVAOAPI = $IVAOAPI;
    }

    public function login(Request $request){
        return Socialite::driver('discord')->scopes(['guilds.join'])->redirect();
    }

    public function loginCallback(Request $request){
        try {
            $user = Socialite::driver('discord')->user();
        } catch (\Exception $e) {
            throw new InvalidPermissionException();
        }

        $member = Member::FromAPIRequest($this->IVAOAPI);
        $member->setDiscordAccessToken($user->token);
        $member->setDiscordId($user->id);
        $this->discordIVAOAuth->validateMember($member);
        return redirect('/success');
    }
}
