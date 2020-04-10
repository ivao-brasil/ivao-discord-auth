<?php

namespace App\Exceptions;

use Exception;

class InvalidIVAOTokenException extends Exception
{
    public function render($request){
    return view('houston', ['text' =>  __('text.invalidTokenException')]);
}
}
