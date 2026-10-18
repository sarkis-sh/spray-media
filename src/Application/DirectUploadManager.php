<?php

namespace SprayMedia\Application;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SprayMedia\Contracts\DirectUploadPolicyInterface;
use SprayMedia\Contracts\MediaItemRepositoryInterface;
use SprayMedia\Contracts\UploadUrlSignerInterface;
use SprayMedia\Domain\DirectUploads\UploadDecision;
use SprayMedia\Domain\DirectUploads\UploadIntent;
use SprayMedia\Domain\Exceptions\DirectUploadsDisabledException;
use SprayMedia\Domain\Exceptions\InvalidUploadTokenException;
use SprayMedia\Domain\Exceptions\UploadExpiredException;
use SprayMedia\Domain\Exceptions\UploadNotReceivedException;
use SprayMedia\Domain\Exceptions\UploadRejectedException;
use SprayMedia\Domain\Models\MediaItem;
use Throwable;

/**
 * Class DirectUploadManager
 *
 * Uploads that go from the client straight to the disk (see the
 * 'direct_uploads' section of the config): step 1 gives an upload URL and
 * a token, step 3 takes the token back, checks the file and makes it a
 * media item. Nothing is written to the database before step 3; the token
 * carries the upload, encrypted with the application key.
 *
 * @package SprayMedia\Application
 */
class DirectUploadManager
{
    private const TOKEN_VERSION = 1;

    public function __construct(
        protected DirectUploadPolicyInterface $policy,
        protected UploadUrlSignerInterface $signer,
        protected MediaItemRepositoryInterface $repository,
    ) {
    }

    /**
     * Step 1: the URL to PUT the file to, the headers to send with it, and
     * the token that completes the upload.
     *
     * @param array $input Validated: filename, mime_type, size, custom_filename, and any custom field.
     * @return array{token: string, upload: array{method: string, url: string, headers: array<string, string>, expires_at: int}}
     */
    public function initiate(array $input): array
    {
        $this->ensureEnabled();

        $intent = $this->intentFrom($input);
        $decision = $this->policy->prepare($intent);
        $this->checkDeclared($intent, $decision);

        $disk = $this->disk();
        $path = $this->pendingPath($intent);
        $urlExpiresAt = now()->addMinutes($this->config('url_expiration_minutes', 10));

        $signed = $this->signer->sign($disk, $path, $urlExpiresAt, $intent->mimeType);

        $token = Crypt::encryptString(json_encode([
            'v' => self::TOKEN_VERSION,
            // Completed only through the policy that issued it: an upload
            // checked under one set of rules cannot be finished under another.
            'policy' => $this->policy::class,
            'disk' => $disk,
            'path' => $path,
            'intent' => $intent->toArray(),
            'decision' => $decision->toArray(),
            'expires_at' => now()->addMinutes($this->config('token_expiration_minutes', 60))->getTimestamp(),
        ]));

        return [
            'token' => $token,
            'upload' => [
                'method' => 'PUT',
                'url' => $signed['url'],
                'headers' => $signed['headers'],
                'expires_at' => $urlExpiresAt->getTimestamp(),
            ],
        ];
    }

    /**
     * Step 3: the file the token stands for, checked and made a media item.
     * A file that fails a check is deleted.
     */
    public function complete(string $token): MediaItem
    {
        $this->ensureEnabled();

        $upload = $this->decode($token);
        $disk = Storage::disk($upload['disk']);
        $path = $upload['path'];
        $intent = UploadIntent::fromArray($upload['intent']);
        $decision = UploadDecision::fromArray($upload['decision']);

        if ($upload['expires_at'] < now()->getTimestamp()) {
            $this->discard($disk, $path);
            throw new UploadExpiredException();
        }

        if (!$disk->exists($path)) {
            throw new UploadNotReceivedException();
        }

        try {
            $size = (int) $disk->size($path);
            $mimeType = $this->check($disk, $path, $intent, $decision, $size);
            $this->policy->confirm($intent, $decision, $mimeType, $size);
        } catch (Throwable $e) {
            $this->discard($disk, $path);
            throw $e;
        }

        $finalPath = $this->finalPath($decision, $intent);

        // A disk set not to throw answers false instead. Either way, the file
        // is gone: completed at the same moment by another request.
        try {
            $moved = $disk->move($path, $finalPath);
        } catch (Throwable) {
            $moved = false;
        }

        if (!$moved) {
            throw new UploadNotReceivedException();
        }

        $filename = $intent->customFilename !== null && $intent->customFilename !== ''
            ? FilenameSanitizer::sanitize($intent->customFilename)
            : $intent->filename;

        try {
            return $this->repository->create(array_merge($decision->attributes, [
                'path' => $finalPath,
                'disk' => $upload['disk'],
                'filename' => $filename,
                'formatted_filename' => $filename . ($intent->extension !== '' ? '.' . $intent->extension : ''),
                'extension' => $intent->extension,
                'mime_type' => $mimeType,
                'size' => $size,
            ]));
        } catch (Throwable $e) {
            $this->discard($disk, $finalPath);
            throw $e;
        }
    }

    /**
     * Deletes the files that waited for step 3 longer than the given minutes
     * (by default, the token's lifetime). Returns how many were deleted.
     */
    public function prunePending(?int $olderThanMinutes = null): int
    {
        $disk = Storage::disk($this->disk());
        $cutoff = now()->subMinutes($olderThanMinutes ?? $this->config('token_expiration_minutes', 60))->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles($this->pendingRoot()) as $file) {
            if ($disk->lastModified($file) < $cutoff && $disk->delete($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Size as promised and within the limit, type allowed: the type is read
     * from the file's first bytes, never taken from the client.
     */
    protected function check(Filesystem $disk, string $path, UploadIntent $intent, UploadDecision $decision, int $size): string
    {
        if ($size !== $intent->size) {
            throw new UploadRejectedException(UploadRejectedException::SIZE_MISMATCH, ['declared' => $intent->size, 'received' => $size]);
        }

        $maxKb = $this->maxKb($decision);
        if ($maxKb > 0 && $size > $maxKb * 1024) {
            throw new UploadRejectedException(UploadRejectedException::TOO_LARGE, ['size' => $size]);
        }

        $mimeType = $this->sniff($disk, $path);
        $allowed = $this->mimetypes($decision);

        if (!empty($allowed) && !in_array($mimeType, $allowed, true)) {
            throw new UploadRejectedException(UploadRejectedException::TYPE_NOT_ALLOWED, ['mime_type' => $mimeType]);
        }

        return $mimeType;
    }

    /**
     * Step 1 against the policy's own limits, where it set any: the request
     * checked the config's, and a kind of upload may allow other types or
     * another size. No URL is given for a file step 3 would refuse.
     */
    protected function checkDeclared(UploadIntent $intent, UploadDecision $decision): void
    {
        $maxKb = $this->maxKb($decision);
        if ($maxKb > 0 && $intent->size > $maxKb * 1024) {
            throw new UploadRejectedException(UploadRejectedException::TOO_LARGE, ['size' => $intent->size]);
        }

        $allowed = $this->mimetypes($decision);
        if (!empty($allowed) && !in_array($intent->mimeType, $allowed, true)) {
            throw new UploadRejectedException(UploadRejectedException::TYPE_NOT_ALLOWED, ['mime_type' => $intent->mimeType]);
        }
    }

    protected function maxKb(UploadDecision $decision): int
    {
        return $decision->maxKb ?? (int) Config::get('spray-media.upload.max_kb', 51200);
    }

    /** @return array<int, string> */
    protected function mimetypes(UploadDecision $decision): array
    {
        return $decision->mimetypes ?? Config::get('spray-media.upload.mimetypes', []);
    }

    /** The type the file's contents show, from its first bytes alone. */
    protected function sniff(Filesystem $disk, string $path): string
    {
        $stream = $disk->readStream($path);
        $head = is_resource($stream) ? stream_get_contents($stream, (int) $this->config('sniff_bytes', 4096)) : false;

        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($head === false || $head === '') {
            return 'application/octet-stream';
        }

        return (new \finfo(FILEINFO_MIME_TYPE))->buffer($head) ?: 'application/octet-stream';
    }

    protected function intentFrom(array $input): UploadIntent
    {
        $name = (string) $input['filename'];
        $extension = strtolower(preg_replace('/[^A-Za-z0-9]/', '', pathinfo($name, PATHINFO_EXTENSION)));

        return new UploadIntent(
            FilenameSanitizer::sanitize(pathinfo($name, PATHINFO_FILENAME)),
            $extension,
            (string) $input['mime_type'],
            (int) $input['size'],
            $input['custom_filename'] ?? null,
            Arr::except($input, ['filename', 'mime_type', 'size', 'custom_filename']),
        );
    }

    /** @return array{disk: string, path: string, intent: array, decision: array, expires_at: int} */
    protected function decode(string $token): array
    {
        try {
            $upload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            throw new InvalidUploadTokenException();
        }

        $valid = is_array($upload)
            && ($upload['v'] ?? null) === self::TOKEN_VERSION
            && ($upload['policy'] ?? null) === $this->policy::class
            && is_string($upload['disk'] ?? null)
            && is_string($upload['path'] ?? null)
            && str_starts_with($upload['path'], $this->pendingRoot() . '/')
            && is_array($upload['intent'] ?? null)
            && is_array($upload['decision'] ?? null)
            && is_int($upload['expires_at'] ?? null);

        if (!$valid) {
            throw new InvalidUploadTokenException();
        }

        return $upload;
    }

    protected function pendingPath(UploadIntent $intent): string
    {
        $name = (string) Str::uuid() . ($intent->extension !== '' ? '.' . $intent->extension : '');

        return $this->pendingRoot() . '/' . date('Y/m/d') . '/' . $name;
    }

    /** Where an ordinary upload would put it: base_dir, the directory, the month. */
    protected function finalPath(UploadDecision $decision, UploadIntent $intent): string
    {
        $baseDir = trim((string) Config::get('spray-media.base_dir', 'uploads'), '/');
        $directory = $decision->directory !== null ? trim($decision->directory, '/') : '';
        $prefix = implode('/', array_filter([$baseDir, $directory, date('Y/m')], fn ($part) => $part !== ''));

        return $prefix . '/' . Str::random(40) . ($intent->extension !== '' ? '.' . $intent->extension : '');
    }

    protected function pendingRoot(): string
    {
        $baseDir = trim((string) Config::get('spray-media.base_dir', 'uploads'), '/');
        $pending = trim((string) $this->config('pending_dir', 'pending'), '/');

        return implode('/', array_filter([$baseDir, $pending], fn ($part) => $part !== ''));
    }

    protected function disk(): string
    {
        return (string) ($this->config('disk') ?: Config::get('spray-media.disk', 'local'));
    }

    protected function ensureEnabled(): void
    {
        if (!$this->config('enabled', false)) {
            throw new DirectUploadsDisabledException();
        }
    }

    protected function discard(Filesystem $disk, string $path): void
    {
        try {
            $disk->delete($path);
        } catch (Throwable) {
            // prune-pending clears what is left.
        }
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return Config::get('spray-media.direct_uploads.' . $key, $default);
    }
}
