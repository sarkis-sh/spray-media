<?php

namespace SprayMedia\Infrastructure\DirectUploads;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use SprayMedia\Contracts\UploadUrlSignerInterface;
use SprayMedia\Domain\Exceptions\DirectUploadsUnsupportedException;
use Throwable;

/**
 * Signs through the disk itself: Laravel's temporaryUploadUrl(), which an S3
 * disk provides (Amazon S3, Cloudflare R2, MinIO, ...).
 *
 * The client is told to send the declared Content-Type, which the disk then
 * keeps with the file. The Host header the SDK lists is left out: every HTTP
 * client sets it from the URL, and some refuse to have it set by hand.
 *
 * @package SprayMedia\Infrastructure\DirectUploads
 */
class DiskUploadUrlSigner implements UploadUrlSignerInterface
{
    public function sign(string $disk, string $path, DateTimeInterface $expiresAt, string $mimeType): array
    {
        $filesystem = Storage::disk($disk);

        if (!method_exists($filesystem, 'temporaryUploadUrl')) {
            throw new DirectUploadsUnsupportedException($disk);
        }

        try {
            $signed = $filesystem->temporaryUploadUrl($path, $expiresAt, ['ContentType' => $mimeType]);
        } catch (Throwable $e) {
            throw new DirectUploadsUnsupportedException($disk, $e);
        }

        $headers = ['Content-Type' => $mimeType];
        foreach ($signed['headers'] ?? [] as $name => $value) {
            if (strcasecmp((string) $name, 'host') === 0) {
                continue;
            }
            $headers[$name] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return ['url' => $signed['url'], 'headers' => $headers];
    }
}
