<?php

namespace SprayMedia\Domain\Exceptions;

/**
 * Thrown when deleting a media file or record fails.
 */
class DeleteFailedException extends MediaException
{
    public function __construct()
    {
        parent::__construct('spray-media::messages.delete_failed', 409);
    }
}
