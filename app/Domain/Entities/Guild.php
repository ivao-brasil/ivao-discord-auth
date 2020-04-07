<?php


namespace App\Domain\Entities;

class Guild
{
    private $id;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Guild constructor.
     * @param $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }
}
