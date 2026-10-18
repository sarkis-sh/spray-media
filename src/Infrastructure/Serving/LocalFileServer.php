<?php

namespace SprayMedia\Infrastructure\Serving;

use SprayMedia\Contracts\FileServerInterface;
use SprayMedia\Contracts\MediaItemRepositoryInterface;
use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Domain\Exceptions\FileMissingOnDiskException;
use SprayMedia\Domain\Exceptions\MediaNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Contracts\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class LocalFileServer
 *
 * Handles the logic of serving a file to the client. This includes setting
 * correct headers, handling caching (ETag), and streaming the file content.
 *
 * With 'serving.mode' set to 'redirect', a file on a disk that makes
 * temporary URLs (S3, Cloudflare R2, MinIO, ...) is not streamed: the client
 * is sent to a short-lived URL of the disk, with the same type and the same
 * inline or attachment disposition, and the bytes never pass through PHP.
 *
 * @package SprayMedia\Infrastructure\Serving
 */
class LocalFileServer implements FileServerInterface
{
    protected MediaItemRepositoryInterface $repository;

    public function __construct(MediaItemRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function serve(array $payload): Response
    {
        $media = $this->repository->find($payload['id']);
        if (!$media) {
            throw new MediaNotFoundException($payload['id']);
        }

        $disk = Storage::disk($media->disk);
        $path = $media->path;
        $action = isset($payload['action']) ? MediaAction::tryFrom($payload['action']) : MediaAction::VIEW;

        if (!$disk->exists($path)) {
            Log::warning('Media file missing on disk', ['media_id' => $media->id, 'disk' => $media->disk, 'path' => $path]);
            throw new FileMissingOnDiskException();
        }

        if ($this->shouldRedirect($media->disk, $disk)) {
            return $this->redirectToDisk($disk, $media, $action);
        }

        // Standard Streaming
        $headers = $this->buildHeaders($media, $action, $payload);

        // Handle ETag Caching
        if (isset($headers['ETag']) && Request::header('If-None-Match') === $headers['ETag']) {
            return ResponseFacade::make('', 304, $headers);
        }

        return new StreamedResponse(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            if ($stream === false) {
                throw new NotFoundHttpException();
            }
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }

    /**
     * Redirect only when asked to, and only to a remote disk that can sign its
     * own URLs: a local disk's temporary URL would come back to PHP anyway.
     */
    protected function shouldRedirect(string $diskName, Filesystem $disk): bool
    {
        return Config::get('spray-media.serving.mode', 'stream') === 'redirect'
            && Config::get("filesystems.disks.{$diskName}.driver") !== 'local'
            && method_exists($disk, 'providesTemporaryUrls')
            && $disk->providesTemporaryUrls();
    }

    /**
     * Sends the client to a URL of the disk, valid for a few minutes. The
     * redirect itself may be cached a little less than that.
     */
    protected function redirectToDisk(Filesystem $disk, $media, MediaAction $action): RedirectResponse
    {
        $minutes = max(1, (int) Config::get('spray-media.serving.redirect_expiration_minutes', 5));
        $name = $media->filename . ($media->extension ? '.' . $media->extension : '');
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '', $name) ?: 'file';
        $disposition = ($action === MediaAction::DOWNLOAD) ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE;

        $url = $disk->temporaryUrl($media->path, now()->addMinutes($minutes), [
            'ResponseContentType' => $media->mime_type ?: 'application/octet-stream',
            'ResponseContentDisposition' => HeaderUtils::makeDisposition($disposition, $safeName, $safeName),
        ]);

        return new RedirectResponse($url, 302, [
            'Cache-Control' => 'private, max-age=' . max(0, $minutes * 60 - 30),
        ]);
    }

    /**
     * Builds the necessary HTTP headers for the file response.
     */
    private function buildHeaders($media, MediaAction $action, array $payload): array
    {
        $config = Config::get('spray-media.performance');
        $disposition = ($action === MediaAction::DOWNLOAD) ? 'attachment' : 'inline';

        $headers = [
            'Content-Type' => $media->mime_type,
            'Content-Length' => $media->size,
            'Content-Disposition' => $disposition . '; filename="' . $media->filename . '.' . $media->extension . '"',
        ];

        if (isset($payload['expires_at'])) {
            $remainingSeconds = max(0, $payload['expires_at'] - now()->timestamp);
            $headers['Cache-Control'] = 'private, max-age=' . $remainingSeconds;
        } elseif ($config['cache_control']) {
            $headers['Cache-Control'] = $config['cache_control'];
        }

        if ($config['enable_etag']) {
            $headers['ETag'] = '"' . md5($media->id . $media->updated_at) . '"';
        }

        return $headers;
    }
}
