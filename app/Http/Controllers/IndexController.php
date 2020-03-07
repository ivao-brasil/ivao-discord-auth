<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\IVAOApiService;
use App\Services\Contracts\IVAOApiServiceContract;

class IndexController extends Controller
{

    private $IVAOApiService;

    public function showIndex() {
        return view('index');
    }

    public function login() {
        $ROUTE = env('APP_URL').'/login/callback';
        return redirect()->away("http://login.ivao.aero/index.php?url=$ROUTE");
    }

    public function loginCallback(Request $request){
        $IVAOTOKEN = $request->input('IVAOTOKEN');
        $this->IVAOApiService = new IVAOApiService($IVAOTOKEN);
        $this->IVAOApiService->getUserData();
        print_r($this->IVAOApiService->getFirstName());
    }
}
