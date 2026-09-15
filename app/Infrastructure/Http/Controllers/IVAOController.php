<?php

namespace App\Infrastructure\Http\Controllers;

use App\Domain\Contracts\IVAOApiServiceContract;
use App\Exceptions\InvalidIVAOTokenException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class IVAOController extends Controller
{
    private $IVAOAPI;

    public function __construct(IVAOApiServiceContract $IVAOAPI)
    {
        $this->IVAOAPI = $IVAOAPI;
    }

    public function login()
    {
        return Socialite::driver('ivao')->redirect();
    }

    public function loginCallback(Request $request)
    {
        if ($request->has('error')) {
            throw new InvalidIVAOTokenException();
        }

        try {
            $user = Socialite::driver('ivao')->user();
        } catch (\Exception $e) {
            Log::warning(get_class($e).': '.$e->getMessage(), ['event' => 'ivao.sso.failed']);
            throw new InvalidIVAOTokenException();
        }

        $request->session()->regenerate();
        $this->IVAOAPI->storeUserData($user->getRaw());

        return redirect('/');
    }
}
