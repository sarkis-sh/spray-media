<?php

namespace SprayMedia\Domain\Exceptions;

use Throwable;

/** The disk cannot make temporary upload URLs: a configuration error, not the client's. */
class DirectUploadsUnsupportedException extends MediaException
{
    public function __construct(string $disk, ?Throwable $previous = null)
    {
        parent::__construct('spray-media::messages.direct_uploads_unsupported', 500, [
            'disk' => $disk,
            'reason' => $previous?->getMessage(),
        ]);
    }
}
