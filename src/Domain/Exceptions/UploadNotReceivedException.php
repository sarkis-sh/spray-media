<?php

namespace SprayMedia\Domain\Exceptions;

/** Step 3 came, but the file is not on the disk: never sent, or already completed. */
class UploadNotReceivedException extends MediaException
{
    public function __construct()
    {
        parent::__construct('spray-media::messages.upload_not_received', 422);
    }
}
