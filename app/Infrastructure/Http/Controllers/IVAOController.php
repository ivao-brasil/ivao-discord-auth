<?php

namespace App\Http\Controllers;

use App\Core\Constants;
use Illuminate\Http\Request;
use App\Services\IVAOApiService;


class IVAOController extends Controller
{

    private $IVAOApiService;

    public function showIndex(Request $request) {
        $IVAOTOKEN = $request->session()->get('IVAOTOKEN');
        $this->IVAOApiService = new IVAOApiService($IVAOTOKEN);
        $this->IVAOApiService->getUserData();
        return view('index', ['firstName' => $this->IVAOApiService->getFirstName()]);
    }

    public function login() {
        $ROUTE = env('APP_URL').'/login/callback';
        $IVAO_API_URL = Constants::IVAO_API_URL;
        return redirect()->away("$IVAO_API_URL?url=$ROUTE");
    }

    public function loginCallback(Request $request){
        $IVAOTOKEN = $request->input('IVAOTOKEN');
        $request->session()->put('IVAOTOKEN', $IVAOTOKEN);
        return redirect('/');
    }
}
