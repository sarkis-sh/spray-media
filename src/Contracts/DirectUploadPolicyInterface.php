<?php

namespace SprayMedia\Contracts;

use SprayMedia\Domain\DirectUploads\UploadDecision;
use SprayMedia\Domain\DirectUploads\UploadIntent;

/**
 * Interface DirectUploadPolicyInterface
 *
 * The application's own rules for direct uploads, on top of the package's
 * ('upload.max_kb', 'upload.mimetypes', 'upload.mimes'): a storage quota,
 * who may upload what, where files go, what a media item records about its
 * owner. To refuse, throw: a MediaException, a ValidationException or an
 * HttpException is rendered as any other.
 *
 * @package SprayMedia\Contracts
 */
interface DirectUploadPolicyInterface
{
    /**
     * Step 1, before any upload URL is given.
     */
    public function prepare(UploadIntent $intent): UploadDecision;

    /**
     * Step 3, once the file is on the disk with its real type and size, and
     * before it becomes a media item. A refusal deletes the file.
     */
    public function confirm(UploadIntent $intent, UploadDecision $decision, string $mimeType, int $size): void;
}
