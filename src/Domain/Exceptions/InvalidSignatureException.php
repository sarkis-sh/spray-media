<?php

namespace SprayMedia\Domain\Exceptions;

/**
 * Thrown when the signed URL signature does not match.
 */
class InvalidSignatureException extends MediaException
{
    public function __construct()
    {
        parent::__construct('spray-media::messages.invalid_signature', 403);
    }
}
