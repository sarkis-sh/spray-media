<?php

namespace SprayMedia\Domain\Exceptions;

/**
 * Thrown when storing the uploaded file fails.
 */
class UploadFailedException extends MediaException
{
    public function __construct()
    {
        parent::__construct('spray-media::messages.upload_failed', 500);
    }
}
