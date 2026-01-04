<?php

namespace SprayMedia\Domain\Exceptions;

/**
 * Thrown when a signed media link has expired.
 */
class ExpiredLinkException extends MediaException
{
    public function __construct()
    {
        parent::__construct('spray-media::messages.link_expired', 403);
    }
}
