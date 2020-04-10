<?php


namespace App\Infrastructure\Http\Controllers;
use App\Application\Contracts\DiscordIVAOAuthServiceInterface;
use App\Domain\Contracts\IVAOApiServiceContract;
use App\Domain\Entities\Member;
use Illuminate\Http\Request;
use Socialite;

class DiscordController extends Controller
{
    private $discordIVAOAuth;
    private $IVAOAPI;

    /**
     * DiscordController constructor.
     * @param $discordIVAOAuth
     */
    public function __construct(DiscordIVAOAuthServiceInterface $discordIVAOAuth, IVAOApiServiceContract $IVAOAPI)
    {
        $this->discordIVAOAuth = $discordIVAOAuth;
        $this->IVAOAPI = $IVAOAPI;
    }

    public function login(Request $request){
        return Socialite::with('discord')->scopes(['guilds.join'])->redirect();
    }

    public function loginCallback(Request $request){
        $user = Socialite::driver('discord')->user();
        $request->session()->put('DISCORD_TOKEN', $user->token);
        $member = Member::FromAPIRequest($this->IVAOAPI);
        $member->setDiscordAccessToken($user->token);
        $member->setDiscordId($user->id);
        $this->discordIVAOAuth->validateMember($member);
        return redirect('/success');
    }
}
