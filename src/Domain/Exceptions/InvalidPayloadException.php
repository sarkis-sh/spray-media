<?php

namespace SprayMedia\Domain\Exceptions;

/**
 * Thrown when the signed payload cannot be decoded or is malformed.
 */
class InvalidPayloadException extends MediaException
{
    public function __construct()
    {
        parent::__construct('spray-media::messages.invalid_payload', 400);
    }
}
