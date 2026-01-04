<?php

namespace SprayMedia\Domain\Exceptions;

/**
 * Thrown when a media record cannot be found.
 */
class MediaNotFoundException extends MediaException
{
    public function __construct(int|string $id)
    {
        parent::__construct('spray-media::messages.media_not_found', 404, ['id' => $id]);
    }
}
