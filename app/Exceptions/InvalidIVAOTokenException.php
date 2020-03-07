<?php

namespace App\Exceptions;

use Exception;

class InvalidIVAOTokenException extends Exception
{
    public function render($request){
        return response("INVALID IVAO TOKEN",400);
    }
}
