<?php

namespace SprayMedia\Domain\Exceptions;

/**
 * Thrown when the media file path does not exist on the filesystem.
 */
class FileMissingOnDiskException extends MediaException
{
    public function __construct()
    {
        parent::__construct('spray-media::messages.file_missing_on_disk', 404);
    }
}
