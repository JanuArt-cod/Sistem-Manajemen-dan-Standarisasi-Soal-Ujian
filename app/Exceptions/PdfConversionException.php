<?php

namespace App\Exceptions;

use RuntimeException;

class PdfConversionException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $userMessage
    ) {
        parent::__construct($userMessage);
    }
}