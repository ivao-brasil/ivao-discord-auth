<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Application\Admin\AssignableRoles;
use App\Domain\Contracts\IVAOApiServiceContract;
use App\Domain\Contracts\RolesServiceContract;
use App\Domain\Entities\RoleRule;
use App\Infrastructure\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RuleController extends Controller
{
    private $rolesService;

    public function __construct(RolesServiceContract $rolesService)
    {
        $this->rolesService = $rolesService;
    }

    public function index(): JsonResponse
    {
        return response()->json($this->rolesService->rules()->map->toArray());
    }

    public function update(Request $request, AssignableRoles $assignableRoles, IVAOApiServiceContract $IVAOAPI): JsonResponse
    {
        $ratings = fn (string $type) => Rule::in(array_keys(config("brauth.ratings.{$type}")));

        $data = $request->validate([
            'rules' => ['present', 'array', 'max:100'],
            'rules.*.id' => ['required', 'string', 'max:40'],
            // Rules written before the admin had names carry none, and refusing them
            // would make every other rule unsavable
            'rules.*.name' => ['nullable', 'string', 'max:60'],
            'rules.*.roles' => ['required', 'array', 'min:1'],
            'rules.*.roles.*' => ['string', 'regex:/^\d+$/'],
            'rules.*.staff' => ['array'],
            'rules.*.staff.*' => ['string', 'max:20'],
            'rules.*.includeTrial' => ['boolean'],
            'rules.*.divisionMode' => [Rule::in([RoleRule::DIVISION_ANY, RoleRule::DIVISION_IN, RoleRule::DIVISION_NOT_IN])],
            'rules.*.divisions' => ['array'],
            'rules.*.divisions.*' => ['string', 'max:3'],
            'rules.*.minAtcRating' => ['nullable', 'integer', $ratings('atc')],
            'rules.*.minPilotRating' => ['nullable', 'integer', $ratings('pilot')],
            'rules.*.minHours' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'rules.*.requiresGca' => ['boolean'],
            'rules.*.requiresVaOwnership' => ['boolean'],
        ]);

        $rules = RoleRule::collection($data['rules']);

        $refused = $assignableRoles->refused($rules->flatMap->getRoles()->unique()->values()->all());
        if ($refused) {
            throw ValidationException::withMessages(['rules' => __('admin.errors.rolesNotAssignable')]);
        }

        $this->rolesService->saveAllRoles($rules->map->toArray()->all());

        Log::notice('Role rules updated', [
            'event' => 'roles.updated',
            'admin' => $IVAOAPI->getUserData()['id'],
            'rules' => $rules->count(),
        ]);

        return response()->json($rules->map->toArray());
    }
}
