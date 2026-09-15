<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Application\Sync\MemberSyncService;
use App\Domain\Contracts\IVAOApiServiceContract;
use App\Infrastructure\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __invoke(IVAOApiServiceContract $IVAOAPI): View
    {
        $user = $IVAOAPI->getUserData();

        return view('admin', [
            'settings' => [
                'user' => ['vid' => (string) $user['id'], 'name' => $user['firstName']],
                'minHours' => config('brauth.min_hours'),
                'ratings' => config('brauth.ratings'),
                'lastSync' => Cache::get(MemberSyncService::LAST_RUN_CACHE_KEY),
                'syncTime' => config('brauth.sync.time'),
                'text' => __('admin'),
            ],
        ]);
    }
}
