<?php

namespace SprayMedia\Domain\Exceptions;

/**
 * Thrown when the requested media action is not allowed.
 */
class InvalidActionException extends MediaException
{
    public function __construct()
    {
        parent::__construct('spray-media::messages.invalid_action', 400);
    }
}
