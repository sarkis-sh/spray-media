<?php

namespace SprayMedia\Domain\Exceptions;

class DirectUploadsDisabledException extends MediaException
{
    public function __construct()
    {
        parent::__construct('spray-media::messages.direct_uploads_disabled', 404);
    }
}
