<?php

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidPhoneNumberException extends InvalidArgumentException
{
    public static function for(string $phone): self
    {
        return new self("Il numero di telefono \"{$phone}\" non è valido.");
    }
}
