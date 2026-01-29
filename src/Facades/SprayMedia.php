<?php

namespace SprayMedia\Facades;

use SprayMedia\Application\MediaItemManager;
use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Domain\Models\MediaItem;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array uploadFile(UploadedFile $file, ?string $directory = null, ?string $disk = null)
 * @method static bool deleteFile(MediaItem $media)
 * @method static string getAbsolutePath(MediaItem $media)
 * @method static MediaItem createMediaItem(array $data)
 * @method static bool updateMediaItem(mixed $id, array $data)
 * @method static bool deleteMediaItem(mixed $id)
 * @method static MediaItem findMediaItemOrFail(mixed $mediaId)
 * @method static array validate(array $request)
 * @method static Response serve(array $payload)
 * @method static string generateProtectedUrl(MediaItem $media, MediaAction $action = MediaAction::VIEW, array $options = [])
 * @method static array generateProtectedUrls(iterable $mediaItems, MediaAction $action = MediaAction::VIEW, array $options = [])
 * @method static string generateProtectedViewUrl(MediaItem $media, array $options = [])
 * @method static string generateProtectedDownloadUrl(MediaItem $media, array $options = [])
 * @method static string generateProtectedTemporaryUrl(MediaItem $media, int $minutes, MediaAction $action = MediaAction::VIEW, array $options = [])
 *
 * @see MediaItemManager
 * @mixin MediaItemManager
 */
class SprayMedia extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'spray-media';
    }
}
