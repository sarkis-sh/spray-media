<?php

namespace SprayMedia\Domain\Exceptions;

use Exception;

/**
 * Base domain exception for media errors with HTTP status and translation key.
 */
abstract class MediaException extends Exception
{
    protected int $status;
    protected string $messageKey;

    public function __construct(string $messageKey, int $status, array $context = [])
    {
        parent::__construct(json_encode($context));
        $this->messageKey = $messageKey;
        $this->status = $status;
    }

    /** HTTP status to return for this domain error. */
    public function getStatus(): int
    {
        return $this->status;
    }

    /** Translation key for the human-readable error message. */
    public function getMessageKey(): string
    {
        return $this->messageKey;
    }
}
