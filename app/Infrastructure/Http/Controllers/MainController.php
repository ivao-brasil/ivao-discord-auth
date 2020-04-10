<?php


namespace App\Infrastructure\Http\Controllers;
use App\Domain\Contracts\IVAOApiServiceContract;
use Illuminate\Http\Request;
use App\Domain\Entities\Member;

class MainController extends Controller
{
    private $IVAOAPI;

    /**
     * MainController constructor.
     * @param $IVAOAPI
     */
    public function __construct(IVAOApiServiceContract $IVAOAPI)
    {
        $this->IVAOAPI = $IVAOAPI;
    }

    public function showIndex(Request $request) {
        $member = Member::FromAPIRequest($this->IVAOAPI);
        return view('index', ['firstName' => $member->getFirstName()]);
    }
}
