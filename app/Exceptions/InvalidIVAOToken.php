<?php

namespace App\Exceptions;

use Exception;

class InvalidIVAOToken extends Exception
{
    public function render($request){
        return response("INVALID IVAO TOKEN",400);
    }
}
