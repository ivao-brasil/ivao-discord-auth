<?php


namespace App\Domain\Entities;

use App\Domain\Contracts\GuildServiceContract;

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

    static function FromService(GuildServiceContract $guild){
        return new self($guild->getGuildId());
    }
}
