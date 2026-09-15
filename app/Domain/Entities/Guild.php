<?php


namespace App\Domain\Entities;

use App\Domain\Contracts\GuildServiceContract;

class Guild
{
    private $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    static function FromService(GuildServiceContract $guild)
    {
        return new self($guild->getGuildId());
    }

    public function getId()
    {
        return $this->id;
    }
}
