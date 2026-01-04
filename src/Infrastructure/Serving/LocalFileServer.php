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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class LocalFileServer
 *
 * Handles the logic of serving a file to the client. This includes setting
 * correct headers, handling caching (ETag), and streaming the file content.
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
