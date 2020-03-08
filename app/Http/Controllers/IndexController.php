<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\IVAOApiService;


class IndexController extends Controller
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
        return redirect()->away("http://login.ivao.aero/index.php?url=$ROUTE");
    }

    public function loginCallback(Request $request){
        $IVAOTOKEN = $request->input('IVAOTOKEN');
        $this->IVAOApiService = new IVAOApiService($IVAOTOKEN);
        $this->IVAOApiService->getUserData();
        $request->session()->put('IVAOTOKEN', $IVAOTOKEN);
        return redirect('/');
    }
}
