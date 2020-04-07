<?php

namespace App\Http\Middleware;

use App\Domain\Contracts\IVAOApiServiceContract;
use Closure;


class Admin
{
    private $IVAOAPI;

    /**
     * Admin constructor.
     * @param $IVAOAPI
     */
    public function __construct(IVAOApiServiceContract $IVAOAPI)
    {
        $this->IVAOAPI = $IVAOAPI;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $vids = explode(":", env('ADMIN_VIDS'));
        $memberData = $this->IVAOAPI->getUserData();
        if(in_array($memberData['vid'], $vids)) {
            return $next($request);
        } else {
            return redirect()->route('home');
        }
    }
}
