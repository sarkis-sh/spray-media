<?php

namespace SprayMedia\Http\Services;

use SprayMedia\Application\FilenameSanitizer;
use SprayMedia\Application\MediaItemManager;
use SprayMedia\Domain\Models\MediaItem;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP-facing service that adapts requests to the core MediaItemManager.
 * Keeps HTTP concerns (Request, validated arrays) outside the core.
 */
class MediaItemHttpService
{
    protected MediaItemManager $mediaManager;

    public function __construct(
        MediaItemManager $mediaManager
    ) {
        $this->mediaManager = $mediaManager;
    }

    /**
     * Upload a file and persist its MediaItem record.
     *
     * @param array $validatedData Validated data from StoreMediaItemRequest (includes 'file', optional 'custom_filename', custom rules).
     * @return MediaItem The created MediaItem model.
     */
    public function upload(array $validatedData): MediaItem
    {
        $data = $this->mediaManager->uploadFile($validatedData['file']);

        if (isset($validatedData['custom_filename'])) {
            $safeName = FilenameSanitizer::sanitize($validatedData['custom_filename']);
            $data['formatted_filename'] = $safeName . '.' . $data['extension'];
            $data['filename'] = $safeName;
        }

        // Avoid persisting the UploadedFile instance itself
        unset($validatedData['file']);

        $finalData = array_merge($data, $validatedData);

        return $this->mediaManager->createMediaItem($finalData);
    }

    /**
     * Serve MediaItem using signed query parameters from the request.
     */
    public function serve(Request $request): Response
    {
        $payload = $this->mediaManager->validate($request->query());
        return $this->mediaManager->serve($payload);
    }

    /**
     * Update only the filename (not file contents) of a MediaItem record.
     */
    public function updateFilename($id, $newFilename): MediaItem
    {
        $media = $this->mediaManager->findMediaItemOrFail($id);
        $safeName = FilenameSanitizer::sanitize($newFilename);
        $data = [
            'formatted_filename' => $safeName . '.' . $media->extension,
            'filename' => $safeName,
        ];
        $this->mediaManager->updateMediaItem($id, $data);

        return $media->fresh();
    }

    /**
     * Delete a MediaItem record and its physical file.
     */
    public function delete($mediaId): bool
    {
        $media = $this->mediaManager->findMediaItemOrFail($mediaId);

        $this->mediaManager->deleteFile($media);

        return $this->mediaManager->deleteMediaItem($mediaId);
    }
}
