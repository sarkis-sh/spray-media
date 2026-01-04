<?php

namespace SprayMedia\Contracts;

use Illuminate\Http\UploadedFile;
use SprayMedia\Domain\Models\MediaItem;

/**
 * Interface FileUploaderInterface
 *
 * Defines the contract for a file storage strategy. Implementations of this
 * interface are responsible for physically storing, deleting, and providing
 * paths to media files on a given filesystem (e.g., local disk, S3).
 *
 * @package SprayMedia\Contracts
 */
interface FileUploaderInterface
{
    /**
    * Stores the uploaded file and extracts its metadata.
    *
    * @param UploadedFile $file The file object from the request.
    * @param string|null $directory Optional relative subdirectory under the base_dir.
    * @param string|null $disk Optional filesystem disk; defaults to config('spray-media.disk').
    * @return array<string, mixed> Metadata ready for persistence. Expected keys:
    *               - path (string) stored path
    *               - disk (string) disk used
    *               - filename (string) original name without extension
    *               - formatted_filename (string) original full name
    *               - extension (string) extension without dot
    *               - mime_type (string|null) mime type
    *               - size (int) size in bytes
     */
    public function upload(UploadedFile $file, ?string $directory = null, ?string $disk = null): array;

    /**
     * Deletes a file from the physical storage.
     *
     * @param MediaItem $media The MediaItem model containing file details.
     * @return bool True on success, false on failure.
     */
    public function delete(MediaItem $media): bool;

    /**
     * Gets the absolute filesystem path for a given media object.
     * This is essential for serving files via PHP (e.g., BinaryFileResponse)
     * or for any server-side file processing.
     *
     * @param MediaItem $media The media object.
     * @return string The full, absolute path on the filesystem.
     */
    public function getAbsolutePath(MediaItem $media): string;
}
