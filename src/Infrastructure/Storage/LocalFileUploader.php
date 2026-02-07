<?php

namespace SprayMedia\Infrastructure\Storage;

use SprayMedia\Application\FilenameSanitizer;
use SprayMedia\Domain\Models\MediaItem;
use SprayMedia\Contracts\FileUploaderInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

/**
 * Class LocalFileUploader
 *
 * Implements the FileUploaderInterface using Laravel's Filesystem.
 * This class is responsible for the physical storage of files.
 *
 * @package SprayMedia\Infrastructure\Storage
 */
class LocalFileUploader implements FileUploaderInterface
{
    /**
     * {@inheritdoc}
     *
     * This implementation uses the UploadedFile::store() method for a secure,
     * stream-based upload process handled by Laravel.
     */
    public function upload(UploadedFile $file, ?string $directory = null, ?string $disk = null): array
    {
        // 1. Determine the target disk, falling back to the default from the config file.
        $targetDisk = $disk ?? Config::get('spray-media.disk', 'public');

        // 2. Construct the target directory path.
        // We get the base directory from the config to avoid hardcoding.
        $baseDir = Config::get('spray-media.base_dir', 'uploads');
        $dateDir = date('Y/m');
        $fullDirectory = $directory ? "{$baseDir}/{$directory}/{$dateDir}" : "{$baseDir}/{$dateDir}";

        // 3. Store the file using Laravel's recommended method.
        // `store()` automatically generates a unique filename and returns the full path.
        // We pass the disk name in the options array, which is the correct way.
        $path = $file->store($fullDirectory, ['disk' => $targetDisk]);

        $mimeType = $file->getMimeType();

        if ($mimeType === 'application/octet-stream') {
            $mimeType = Storage::disk($targetDisk)->mimeType($path);
        }

        $originalBaseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $sanitizedBaseName = FilenameSanitizer::sanitize($originalBaseName);
        $extension = $file->getClientOriginalExtension();
        $sanitizedFormatted = $sanitizedBaseName . ($extension ? '.' . $extension : '');

        // 4. Return a structured array with all necessary metadata.
        return [
            'path'                  => $path,
            'disk'                  => $targetDisk,
            'filename'              => $sanitizedBaseName,
            'formatted_filename'    => $sanitizedFormatted,
            'extension'             => $extension,
            'mime_type'             => $mimeType,
            'size'                  => $file->getSize(),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Delegates file deletion to Laravel's Storage facade for consistency.
     */
    public function delete(MediaItem $media): bool
    {
        $deleted = Storage::disk($media->disk)->delete($media->path);
        if (!$deleted) {
            Log::warning('Failed to delete media file', ['media_id' => $media->id, 'disk' => $media->disk, 'path' => $media->path]);
        }
        return $deleted;
    }

    /**
     * {@inheritdoc}
     *
     * Uses the Storage facade to get the correct absolute path, resolving
     * the root path from `config/filesystems.php` automatically.
     */
    public function getAbsolutePath(MediaItem $media): string
    {
        return Storage::disk($media->disk)->path($media->path);
    }
}
