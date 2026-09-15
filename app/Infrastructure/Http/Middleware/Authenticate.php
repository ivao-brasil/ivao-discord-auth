<?php

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Contracts\IVAOApiServiceContract;
use Closure;

class Authenticate
{
    private $IVAOAPI;

    public function __construct(IVAOApiServiceContract $IVAOAPI)
    {
        $this->IVAOAPI = $IVAOAPI;
    }

    public function handle($request, Closure $next)
    {
        if ($this->IVAOAPI->getUserData() === null) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
