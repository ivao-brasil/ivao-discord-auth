<?php


namespace App\Domain\Entities;

use App\Domain\Contracts\RolesServiceContract;
use Illuminate\Support\Collection;

class Roles
{
    private $suffix;
    private $id;

    public function __construct($role)
    {
        $this->suffix = $role['sulfix'];
        $this->id = $role['id'];
    }

    /** @return  Collection */
    public static function FromAPIRequest(RolesServiceContract $rolesService)
    {
        $rolesData = Collection::make($rolesService->getAllRoles());

        $ids = Collection::make($rolesData->reduce(function ($array, $roleData) {
            if (!is_array($array))
                $array = array();
            return array_merge($array, $roleData->id);
        }));

        return $ids->map(function ($id, $key) use ($rolesData) {
            return new self([
                'id' => $id,
                'sulfix' => $rolesData[$rolesData->search(function ($roleData, $key) use ($id) {
                    return in_array($id, $roleData->id);
                })]->sulfix
            ]);
        });

    }

    /**
     * @return mixed
     */
    public function getSuffix()
    {
        return $this->suffix;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
}
