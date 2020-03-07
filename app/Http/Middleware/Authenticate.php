<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\IVAOApiService;

class Authenticate
{
    public function handle($request, Closure $next)
    {
        $IVAOTOKEN = $request->session()->get('IVAOTOKEN');
        $IVAOAPI = new IVAOApiService($IVAOTOKEN);
        $IVAOAPI->getUserData();
        if($IVAOAPI->getFirstName() != ""){
            return $next($request);
        }
        else
        {
            return route('login');
        }
    }
}
