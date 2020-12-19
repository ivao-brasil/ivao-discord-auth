<?php


namespace App\Infrastructure\Http\Controllers;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOApiServiceContract;
use App\Domain\Entities\Guild;
use App\Infrastructure\Services\ConsentmentService;
use Illuminate\Http\Request;
use App\Domain\Entities\Member;

class MainController extends Controller
{
    private $IVAOAPI;
    private $consentmentService;
    private $guildService;

    /**
     * MainController constructor.
     * @param $IVAOAPI
     */
    public function __construct(IVAOApiServiceContract $IVAOAPI, ConsentmentService $consentmentService, GuildServiceContract $guildService)
    {
        $this->IVAOAPI = $IVAOAPI;
        $this->consentmentService = $consentmentService;
        $this->guildService = $guildService;
    }

    public function showIndex(Request $request) {
        $member = Member::FromAPIRequest($this->IVAOAPI);
        return view('index', ['firstName' => $member->getFirstName()]);
    }

    public function revoke(Request $request) {
        $member = Member::FromAPIRequest($this->IVAOAPI);
        $accounts = $this->consentmentService->getActiveAccounts($member->getVid());
        $guild = Guild::FromService($this->guildService);

        $this->consentmentService->remove($member->getVid());

        foreach($accounts as $account) {
            $this->guildService->removeFromServer($account['discordId'], $guild);
        }

        return redirect('/');
    }
}
