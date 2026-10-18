<?php

namespace SprayMedia\Domain\Exceptions;

/**
 * The uploaded file is not the one step 1 allowed: another size, a type not
 * allowed, too large. It has been deleted.
 */
class UploadRejectedException extends MediaException
{
    public const SIZE_MISMATCH = 'upload_size_mismatch';
    public const TOO_LARGE = 'upload_too_large';
    public const TYPE_NOT_ALLOWED = 'upload_type_not_allowed';

    public function __construct(private string $reason, array $context = [])
    {
        parent::__construct('spray-media::messages.' . $reason, 422, $context);
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
