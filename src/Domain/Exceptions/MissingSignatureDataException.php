<?php

namespace SprayMedia\Domain\Exceptions;

/**
 * Thrown when required signature or data parameters are absent.
 */
class MissingSignatureDataException extends MediaException
{
    public function __construct()
    {
        parent::__construct('spray-media::messages.missing_signature_data', 400);
    }
}
