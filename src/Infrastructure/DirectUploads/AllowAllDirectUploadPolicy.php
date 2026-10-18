<?php

namespace SprayMedia\Infrastructure\DirectUploads;

use SprayMedia\Contracts\DirectUploadPolicyInterface;
use SprayMedia\Domain\DirectUploads\UploadDecision;
use SprayMedia\Domain\DirectUploads\UploadIntent;

/**
 * The default policy: no rules beyond the package's own. The extra fields of
 * the request ('upload.custom_rules') become attributes of the media item,
 * as they do for an ordinary upload.
 *
 * @package SprayMedia\Infrastructure\DirectUploads
 */
class AllowAllDirectUploadPolicy implements DirectUploadPolicyInterface
{
    public function prepare(UploadIntent $intent): UploadDecision
    {
        return new UploadDecision(null, $intent->input);
    }

    public function confirm(UploadIntent $intent, UploadDecision $decision, string $mimeType, int $size): void
    {
        //
    }
}
