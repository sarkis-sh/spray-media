<?php

namespace SprayMedia\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use SprayMedia\Application\DirectUploadManager;
use SprayMedia\Contracts\ResponseAdapterInterface;
use SprayMedia\Http\Requests\CompleteDirectUploadRequest;
use SprayMedia\Http\Requests\InitiateDirectUploadRequest;
use SprayMedia\Http\Resources\MediaItemResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uploads that go from the client straight to the disk: step 1 (initiate)
 * and step 3 (complete). Step 2, the upload itself, never reaches PHP.
 */
class DirectUploadController
{
    public function __construct(
        private DirectUploadManager $uploads,
        private ResponseAdapterInterface $responder
    ) {
    }

    public function initiate(InitiateDirectUploadRequest $request): JsonResponse
    {
        return $this->responder->success(
            $this->uploads->initiate($request->validated()),
            Lang::get('spray-media::messages.upload_url_created'),
            Response::HTTP_OK
        );
    }

    /** The media item, exactly as an ordinary upload returns it. */
    public function complete(CompleteDirectUploadRequest $request): JsonResponse
    {
        $media = $this->uploads->complete($request->validated('token'));
        $resourceClass = Config::get('spray-media.resource', MediaItemResource::class);

        return $this->responder->success(
            new $resourceClass($media),
            Lang::get('spray-media::messages.upload_success'),
            Response::HTTP_CREATED
        );
    }
}
