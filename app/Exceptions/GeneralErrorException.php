<?php

namespace App\Exceptions;

use Exception;

class GeneralErrorException extends Exception
{
    public function render($request){
        return view('houston', ['text' => __('text.generalException')]);
    }
}
