<?php

declare(strict_types=1);

namespace SendKit\Exceptions;

use Exception;

class SendKitException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $name = null,
    ) {
        parent::__construct($message, $status);
    }
}
