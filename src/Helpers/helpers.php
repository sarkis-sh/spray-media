<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use SprayMedia\Application\MediaItemManager;
use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Domain\Models\MediaItem;
use SprayMedia\Http\Resources\MediaItemResource;

if (!function_exists('media_item_generate_protected_url')) {
    /**
     * Generate a signed URL for a MediaItem with the given action.
     *
     * @param MediaItem $media Target MediaItem model.
     * @param MediaAction $action Action type (view/download).
     * @param array $options Supported keys:
     *               - expiration_minutes (int|null): override expiry; null for no expiry.
     *               - metadata (array): extra non-sensitive data to embed.
     * @return string Signed URL.
     */
    function media_item_generate_protected_url(MediaItem $media, MediaAction $action = MediaAction::VIEW, array $options = []): string
    {
        return app(MediaItemManager::class)->generateProtectedUrl($media, $action, $options);
    }
}

if (!function_exists('media_item_generate_protected_urls')) {
    /**
     * Generate signed URLs for a set of MediaItems using the same action/options.
     *
     * @param iterable<MediaItem> $mediaItems A collection/array of MediaItem models.
     * @param MediaAction $action Action type (view/download) applied to each item.
     * @param array $options Extra options forwarded to the URL generator (e.g., expiration_minutes, metadata).
     * @return array<int, string> Ordered list of signed URLs matching the input order.
     */
    function media_item_generate_protected_urls(iterable $mediaItems, MediaAction $action = MediaAction::VIEW, array $options = []): array
    {
        return collect($mediaItems)->map(function ($media) use ($action, $options) {
            return media_item_generate_protected_url($media, $action, $options);
        })->all();
    }
}

if (!function_exists('media_item_generate_protected_view_url')) {
    /**
     * Generate a signed view (inline) URL for a MediaItem.
     */
    function media_item_generate_protected_view_url(MediaItem $media, array $options = []): string
    {
        return media_item_generate_protected_url($media, MediaAction::VIEW, $options);
    }
}

if (!function_exists('media_item_generate_protected_download_url')) {
    /**
     * Generate a signed download (attachment) URL for a MediaItem.
     */
    function media_item_generate_protected_download_url(MediaItem $media, array $options = []): string
    {
        return media_item_generate_protected_url($media, MediaAction::DOWNLOAD, $options);
    }
}

if (!function_exists('media_item_generate_protected_temporary_url')) {
    /**
     * Generate a signed URL that expires after the given minutes.
     */
    function media_item_generate_protected_temporary_url(MediaItem $media, int $minutes, MediaAction $action = MediaAction::VIEW, array $options = []): string
    {
        $options['expiration_minutes'] = $minutes;
        return media_item_generate_protected_url($media, $action, $options);
    }
}

if (!function_exists('media_item_with_signed_url')) {
    /**
     * Wrap a MediaItem model in the configured resource and inject a signed URL.
     */
    function media_item_with_signed_url(MediaItem $media, MediaAction $action = MediaAction::VIEW, array $options = []): MediaItemResource
    {
        $resourceClass = Config::get('spray-media.resource', MediaItemResource::class);
        return new $resourceClass($media, $action, $options);
    }
}

if (!function_exists('media_item_collection_with_signed_url')) {
    /**
     * Wrap a collection of MediaItems in the configured resource, each with a signed URL.
     */
    function media_item_collection_with_signed_url(iterable $mediaItems, MediaAction $action = MediaAction::VIEW, array $options = [])
    {
        $resourceClass = Config::get('spray-media.resource', MediaItemResource::class);
        return $resourceClass::collection(collect($mediaItems)->map(fn($media) => new $resourceClass($media, $action, $options)));
    }
}

if (!function_exists('media_item_upload_file')) {
    /**
     * Upload a file via the media item manager and return stored metadata.
     */
    function media_item_upload_file(UploadedFile $file, ?string $directory = null, ?string $disk = null): array
    {
        return app(MediaItemManager::class)->uploadFile($file, $directory, $disk);
    }
}

if (!function_exists('media_item_upload_and_create')) {
    /**
     * Upload a file and persist its MediaItem record in one step.
     */
    function media_item_upload_and_create(UploadedFile $file, array $attributes = [], ?string $directory = null, ?string $disk = null): MediaItem
    {
        $manager = app(MediaItemManager::class);
        $data = $manager->uploadFile($file, $directory, $disk);
        return $manager->createMediaItem(array_merge($data, $attributes));
    }
}

if (!function_exists('media_item_create')) {
    /**
     * Persist a MediaItem record using provided metadata (no upload).
     */
    function media_item_create(array $data): MediaItem
    {
        return app(MediaItemManager::class)->createMediaItem($data);
    }
}

if (!function_exists('find_media_item_or_fail')) {
    /**
     * Find a MediaItem record or throw if missing.
     */
    function find_media_item_or_fail(mixed $id): MediaItem
    {
        return app(MediaItemManager::class)->findMediaItemOrFail($id);
    }
}

if (!function_exists('media_item_delete_file')) {
    /**
     * Delete a media file by id.
     */
    function media_item_delete_file(mixed $id): bool
    {
        $manager = app(MediaItemManager::class);
        $media = $manager->findMediaItemOrFail($id);
        return $manager->deleteFile($media);
    }
}

if (!function_exists('media_item_delete_with_file')) {
    /**
     * Delete a MediaItem record (and its file) by id.
     */
    function media_item_delete_with_file(mixed $id): bool
    {
        $manager = app(MediaItemManager::class);
        $media = $manager->findMediaItemOrFail($id);
        $manager->deleteFile($media);
        return $manager->deleteMediaItem($id);
    }
}

if (!function_exists('media_item_delete')) {
    /**
     * Delete a MediaItem record by model instance.
     */
    function media_item_delete(MediaItem $media): bool
    {
        $manager = app(MediaItemManager::class);
        return $manager->deleteMediaItem($media->id);
    }
}

if (!function_exists('media_item_get_absolute_path')) {
    /**
     * Get absolute filesystem path for a MediaItem.
     */
    function media_item_get_absolute_path(MediaItem $media): string
    {
        return app(MediaItemManager::class)->getAbsolutePath($media);
    }
}