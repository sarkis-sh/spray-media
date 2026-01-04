<?php

namespace SprayMedia\Contracts;

use Symfony\Component\HttpFoundation\Response;

/**
 * Interface FileServerInterface
 *
 * Defines the contract for serving a file to the client based on a validated payload.
 * Implementations should only deal with reading the payload and streaming/redirecting
 * the file (e.g., a file stream or 304).
 *
 * @package SprayMedia\Contracts
 */
interface FileServerInterface
{
    /**
     * Serves a file based on the details provided in the payload.
     *
     * @param array $payload Validated payload (e.g., ['id' => int, 'action' => 'view'|'download', 'expires_at' => int|null, 'metadata' => array]).
     * @return Response The HTTP response to send to the client.
     */
    public function serve(array $payload): Response;
}
