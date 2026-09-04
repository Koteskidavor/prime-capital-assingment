<?php

namespace App\Exceptions;

use Exception;

class InsufficientSharesException extends Exception
{
    protected $message = 'Insufficient shares for this sale.';
}
