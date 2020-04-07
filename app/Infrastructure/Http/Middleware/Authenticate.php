<?php

namespace App\Http\Middleware;

use App\Domain\Contracts\IVAOApiServiceContract;
use Closure;

class Authenticate
{
    private $IVAOAPI;

    /**
     * Authenticate constructor.
     * @param $IVAOAPI
     */
    public function __construct(IVAOApiServiceContract $IVAOAPI)
    {
        $this->IVAOAPI = $IVAOAPI;
    }

    public function handle($request, Closure $next)
    {
        $memberData = $this->IVAOAPI->getUserData();
        if($memberData['firstname'] != '') {
            return $next($request);
        } else {
            return redirect()->route('login');
        }
    }
}
