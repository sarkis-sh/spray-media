<?php

namespace SprayMedia\Contracts;

use DateTimeInterface;

/**
 * Interface UploadUrlSignerInterface
 *
 * Makes the short-lived URL a client sends a file to, and the headers it
 * must send with it.
 *
 * @package SprayMedia\Contracts
 */
interface UploadUrlSignerInterface
{
    /**
     * @return array{url: string, headers: array<string, string>}
     */
    public function sign(string $disk, string $path, DateTimeInterface $expiresAt, string $mimeType): array;
}
