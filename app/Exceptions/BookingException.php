<?php

namespace App\Exceptions;

use App\Enums\BookingFailure;
use RuntimeException;

final class BookingException extends RuntimeException
{
    public function __construct(public readonly BookingFailure $failure)
    {
        parent::__construct($failure->message());
    }
}
