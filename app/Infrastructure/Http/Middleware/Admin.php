<?php

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Contracts\IVAOApiServiceContract;
use Closure;

class Admin
{
    private $IVAOAPI;

    public function __construct(IVAOApiServiceContract $IVAOAPI)
    {
        $this->IVAOAPI = $IVAOAPI;
    }

    public function handle($request, Closure $next)
    {
        $memberData = $this->IVAOAPI->getUserData();

        if (in_array((string) $memberData['id'], config('brauth.admin_vids'), true)) {
            return $next($request);
        }

        return redirect()->route('home');
    }
}
