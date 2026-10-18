<?php

namespace SprayMedia\Domain\Exceptions;

class InvalidUploadTokenException extends MediaException
{
    public function __construct()
    {
        parent::__construct('spray-media::messages.invalid_upload_token', 400);
    }
}
