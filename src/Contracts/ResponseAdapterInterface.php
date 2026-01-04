<?php

namespace SprayMedia\Contracts;

use Illuminate\Http\JsonResponse;

interface ResponseAdapterInterface
{
    /**
     * Build a success JSON response.
     *
     * @param mixed $data Payload to return (resource/array/null).
     * @param string|null $message Optional human-readable message.
     * @param int $status HTTP status code (default 200).
     * @param string|null $token Optional bearer token header value.
     */
    public function success(mixed $data = null, ?string $message = null, int $status = 200, ?string $token = null): JsonResponse;

    /**
     * Build an error JSON response.
     *
     * @param string|null $message Error message.
     * @param int $status HTTP status code.
     * @param array $errors Optional list/dictionary of validation or domain errors.
     * @param mixed $data Optional additional data.
     */
    public function error(?string $message = null, int $status = 500, array $errors = [], mixed $data = null): JsonResponse;
}
