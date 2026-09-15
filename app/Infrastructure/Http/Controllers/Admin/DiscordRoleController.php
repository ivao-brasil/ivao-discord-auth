<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Application\Admin\AssignableRoles;
use App\Infrastructure\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DiscordRoleController extends Controller
{
    public function __invoke(AssignableRoles $roles): JsonResponse
    {
        return response()->json($roles->all());
    }
}
