<?php

namespace SprayMedia\Contracts;

use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Domain\Models\MediaItem;

/**
 * Interface MediaItemUrlGeneratorInterface
 *
 * Defines the contract for creating a secure, signed URL for a media item.
 * Its single responsibility is to generate a URL string containing a signed payload.
 */
interface MediaItemUrlGeneratorInterface
{
    /**
     * Generates a secure, signed URL for the given media item.
     *
     * @param MediaItem $media The MediaItem model instance.
     * @param MediaAction $action The intended action ('view' or 'download').
     * @param array $options Additional options:
     *               - expiration_minutes (int|null): override default expiry; null for no expiry.
     *               - metadata (array): extra non-sensitive data to embed in the payload.
     * @return string The fully-formed, signed URL.
     */
    public function generate(MediaItem $media, MediaAction $action = MediaAction::VIEW, array $options = []): string;
}
