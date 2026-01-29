<?php

namespace SprayMedia\Http\Controllers;

use SprayMedia\Http\Requests\StoreMediaItemRequest;
use SprayMedia\Http\Requests\UpdateMediaItemRequest;
use SprayMedia\Http\Resources\MediaItemResource;
use SprayMedia\Http\Services\MediaItemHttpService;
use SprayMedia\Contracts\ResponseAdapterInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP controller for MediaItem endpoints (upload, update filename, delete, serve).
 */
class MediaItemController
{
    private string $resourceClass;
    public function __construct(
        private MediaItemHttpService $mediaService,
        private ResponseAdapterInterface $responder
    ) {
        $this->resourceClass = Config::get('spray-media.resource', MediaItemResource::class);
    }

    /**
     * Serve a protected MediaItem file using signed URL query parameters.
     */
    public function handle(Request $request): Response
    {
        return $this->mediaService->serve($request);
    }

    /**
     * Upload a file and persist its MediaItem record.
     */
    public function store(StoreMediaItemRequest $request)
    {
        $validatedData = $request->validated();

        $media = $this->mediaService->upload($validatedData);

        return $this->responder->success(
            new $this->resourceClass($media),
            Lang::get('spray-media::messages.upload_success'),
            Response::HTTP_CREATED
        );
    }

    /**
     * Update the filename (without re-upload) for a MediaItem record.
     */
    public function updateFileName(UpdateMediaItemRequest $request, mixed $id): JsonResponse
    {
        $newFileName = $request->validated('new_file_name');
        $media = $this->mediaService->updateFilename($id, $newFileName);

        return $this->responder->success(
            new $this->resourceClass($media),
            Lang::get('spray-media::messages.filename_updated'),
            Response::HTTP_OK
        );
    }

    /**
     * Delete a MediaItem record and its file.
     */
    public function delete(mixed $id): JsonResponse
    {
        $this->mediaService->delete($id);

        return $this->responder->success(
            null,
            Lang::get('spray-media::messages.delete_success'),
            Response::HTTP_OK
        );
    }
}
