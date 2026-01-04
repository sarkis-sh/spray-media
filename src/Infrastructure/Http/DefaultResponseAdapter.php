<?php

namespace SprayMedia\Infrastructure\Http;

use SprayMedia\Contracts\ResponseAdapterInterface;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\JsonResponse;

class DefaultResponseAdapter implements ResponseAdapterInterface
{
    public function success(mixed $data = null, ?string $message = null, int $status = 200, ?string $token = null): JsonResponse
    {
        return Response::json(
            [
                'result' => 'success',
                'message' => $message,
                'model' => $data,
                'error_list' => [],
                'code' => $status,
            ],
            $status,
            $token ? ['Authorization' => $token] : []
        );
    }

    public function error(?string $message = null, int $status = 500, array $errors = [], mixed $data = null): JsonResponse
    {
        return Response::json(
            [
                'result' => 'error',
                'message' => $message,
                'model' => $data,
                'error_list' => $errors,
                'code' => $status,
            ],
            $status
        );
    }
}
