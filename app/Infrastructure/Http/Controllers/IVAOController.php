<?php

namespace App\Infrastructure\Http\Controllers;

use App\Core\Constants;
use App\Exceptions\InvalidIVAOTokenException;
use Illuminate\Http\Request;
//use App\Services\IVAOApiService;


class IVAOController extends Controller
{
    private const IVAO_API_URL = "https://login.ivao.aero/index.php";

    public function login() {
        $ROUTE = env('APP_URL').'/ivao/callback';
        return redirect()->away(self::IVAO_API_URL."?url=$ROUTE");
    }

    public function loginCallback(Request $request){
        $IVAOTOKEN = $request->input('IVAOTOKEN');

        if($IVAOTOKEN != 'error') {
            $request->session()->put('IVAOTOKEN', $IVAOTOKEN);
            return redirect('/');
        }
        else {
            throw new InvalidIVAOTokenException();
        }
    }
}
