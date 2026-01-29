<?php

namespace SprayMedia\Application;

use SprayMedia\Contracts\FileServerInterface;
use SprayMedia\Contracts\FileUploaderInterface;
use SprayMedia\Contracts\MediaItemRepositoryInterface;
use SprayMedia\Contracts\PayloadValidatorInterface;
use SprayMedia\Contracts\MediaItemUrlGeneratorInterface;
use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Domain\Models\MediaItem;
use SprayMedia\Domain\Exceptions\DeleteFailedException;
use SprayMedia\Domain\Exceptions\MediaNotFoundException;
use SprayMedia\Domain\Exceptions\UploadFailedException;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Core application service for MediaItem operations (upload, CRUD, URL generation, serving).
 * This class is framework-agnostic (no Request/Response coupling) so it can be reused
 * from HTTP, CLI, queues, or helpers.
 */
class MediaItemManager
{
    protected FileUploaderInterface $uploader;
    protected MediaItemRepositoryInterface $repository;
    protected MediaItemUrlGeneratorInterface $generator;
    protected PayloadValidatorInterface $validator;
    protected FileServerInterface $fileServer;
    public function __construct(
        FileUploaderInterface $uploader,
        MediaItemRepositoryInterface $repository,
        MediaItemUrlGeneratorInterface $generator,
        PayloadValidatorInterface $validator,
        FileServerInterface $fileServer
    ) {
        $this->uploader = $uploader;
        $this->repository = $repository;
        $this->generator = $generator;
        $this->validator = $validator;
        $this->fileServer = $fileServer;
    }

    /**
     * Store a file on the configured disk and return its metadata.
     *
     * @param UploadedFile $file The uploaded file instance.
     * @param string|null $directory Optional subdirectory under base_dir (e.g., 'avatars').
     * @param string|null $disk Optional disk name; defaults to config('spray-media.disk').
     * @return array<string, mixed> Stored file metadata (path, disk, filename, formatted_filename, extension, mime_type, size).
     */
    public function uploadFile(UploadedFile $file, ?string $directory = null, ?string $disk = null): array
    {
        $data = $this->uploader->upload($file, $directory, $disk);
        if (!$data || !isset($data['path'])) {
            throw new UploadFailedException();
        }

        return $data;
    }

    /**
     * Delete the physical file for a MediaItem model.
     */
    public function deleteFile(MediaItem $model): bool
    {
        $deleted = $this->uploader->delete($model);
        if (!$deleted) {
            throw new DeleteFailedException();
        }

        return true;
    }

    /**
     * Get absolute filesystem path for a MediaItem model.
     */
    public function getAbsolutePath(MediaItem $model): string
    {
        return $this->uploader->getAbsolutePath($model);
    }

    /**
     * Persist a MediaItem record with the given attributes.
     *
     * @param array<string, mixed> $data Attributes matching the MediaItem model fillable fields.
     */
    public function createMediaItem(array $data): MediaItem
    {
        return $this->repository->create($data);
    }

    /**
     * Update an existing MediaItem record by id.
     *
     * @param mixed $id MediaItem id.
     * @param array<string, mixed> $data Attributes to update.
     */
    public function updateMediaItem(mixed $id, array $data): bool
    {
        return $this->repository->update($id, $data);
    }

    /**
     * Delete a MediaItem record by id.
     */
    public function deleteMediaItem(mixed $id): bool
    {
        $deleted = $this->repository->delete($id);
        if (!$deleted) {
            throw new DeleteFailedException();
        }

        return true;
    }

    /**
     * Find a MediaItem record or throw a domain exception if missing.
     */
    public function findMediaItemOrFail(mixed $mediaId): MediaItem
    {
        $media = $this->repository->find($mediaId);
        if (!$media) {
            throw new MediaNotFoundException($mediaId);
        }
        return $media;
    }

    /**
     * Validate a raw request payload (typically query params) and return decoded payload.
     *
     * @param array $request Raw request data containing signed payload (e.g., ['data' => base64, 'signature' => string]).
     * @return array Validated payload (id, action, expires_at|null, metadata array).
     */
    public function validate(array $request): array
    {
        return $this->validator->validate($request);
    }

    /**
     * Serve a media item response based on a validated payload.
     */
    public function serve(array $payload): Response
    {
        return $this->fileServer->serve($payload);
    }

    /**
     * Generate a signed URL for a media item.
     *
     * @param MediaItem $media Target media item.
     * @param MediaAction $action Action type (view/download).
     * @param array $options Supported keys:
     *               - expiration_minutes (int|null): override expiry; null for no expiry.
     *               - metadata (array): extra non-sensitive data to embed.
     */
    public function generateProtectedUrl(MediaItem $media, MediaAction $action = MediaAction::VIEW, array $options = []): string
    {
        return $this->generator->generate($media, $action, $options);
    }

    /**
     * Generate a signed view URL (inline).
     * @param MediaItem $media Target media item.
     * @param array $options Supported keys:
     *               - expiration_minutes (int|null): override expiry; null for no expiry.
     *               - metadata (array): extra non-sensitive data to embed.
     */
    public function generateProtectedViewUrl(MediaItem $media, array $options = []): string
    {
        return $this->generateProtectedUrl($media, MediaAction::VIEW, $options);
    }

    /**
     * Generate a signed download URL (attachment).
     * @param MediaItem $media Target media item.
     * @param array $options Supported keys:
     *               - expiration_minutes (int|null): override expiry; null for no expiry.
     *               - metadata (array): extra non-sensitive data to embed.
     */
    public function generateProtectedDownloadUrl(MediaItem $media, array $options = []): string
    {
        return $this->generateProtectedUrl($media, MediaAction::DOWNLOAD, $options);
    }

    /**
     * Generate a signed URL that expires after the given minutes.
     * @param MediaItem $media Target media.
     * @param int $minutes Expiration minutes.
     * @param MediaAction $action Action type (view/download).
     * @param array $options Supported keys:
     *               - expiration_minutes (int|null): override expiry; null for no expiry.
     *               - metadata (array): extra non-sensitive data to embed.
     */
    public function generateProtectedTemporaryUrl(MediaItem $media, int $minutes, MediaAction $action = MediaAction::VIEW, array $options = []): string
    {
        $options['expiration_minutes'] = $minutes;
        return $this->generateProtectedUrl($media, $action, $options);
    }

    /**
     * Generate signed URLs for a set of media items using the same action/options.
     *
     * @param iterable<MediaItem> $mediaItems A collection/array of MediaItem models.
     * @param MediaAction $action Action type (view/download) applied to each item.
     * @param array $options Extra options forwarded to the URL generator (e.g., expiration_minutes, metadata).
     * @return array<int, string> Ordered list of signed URLs matching the input order.
     */
    public function generateProtectedUrls(iterable $mediaItems, MediaAction $action = MediaAction::VIEW, array $options = []): array
    {
        return collect($mediaItems)->map(function ($media) use ($action, $options) {
            return $this->generator->generate($media, $action, $options);
        })->all();
    }
}
