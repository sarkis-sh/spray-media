<?php

namespace SprayMedia\Domain\Exceptions;

class UploadExpiredException extends MediaException
{
    public function __construct()
    {
        parent::__construct('spray-media::messages.upload_expired', 410);
    }
}
