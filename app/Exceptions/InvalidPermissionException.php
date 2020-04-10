<?php

namespace App\Exceptions;

use Exception;

class InvalidPermissionException extends Exception
{
    public function render($request){
        return view('houston', ['text' => __('text.invalidPermissionException')]);
    }
}
