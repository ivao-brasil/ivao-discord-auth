<?php

namespace App\Exceptions;

use Exception;

class InactiveAccountException extends Exception
{
    protected $reason;

    public function __construct($reason = 'inactive')
    {
        $this->reason = $reason;
        parent::__construct("Account is {$reason}");
    }

    public function getReason()
    {
        return $this->reason;
    }

    public function render($request)
    {
        // Use the appropriate translation key based on the reason
        $translationKey = 'text.accountSuspendedException';
        
        if ($this->reason === 'inactive') {
            $translationKey = 'text.accountInactiveException';
        } elseif ($this->reason === 'suspended') {
            $translationKey = 'text.accountSuspendedException';
        }
        
        return view('houston', ['text' => __($translationKey)]);
    }
}