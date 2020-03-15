<?php

namespace App\Http\Middleware;

use Closure;

use App\Services\IVAOApiService;


class Admin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $vids = explode(':',env('ADMIN_VIDS'));
        $IVAOTOKEN = $request->session()->get('IVAOTOKEN');
        $IVAOAPI = new IVAOApiService($IVAOTOKEN);
        if(in_array($IVAOAPI->getVid(), $vids)){
            return $next($request);
        }
        else {
            return redirect()->route('home');
        }
    }
}
