<?php

namespace SprayMedia\Tests\Unit;

use Illuminate\Support\Facades\Config;
use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Http\Resources\MediaItemResource;
use SprayMedia\Tests\TestCase;

class MediaItemResourceTest extends TestCase
{
    public function test_resource_contains_signed_url_and_expiration(): void
    {
        $media = $this->createMedia();

        $resource = new MediaItemResource($media, MediaAction::VIEW, []);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('url', $array);
        $this->assertArrayHasKey('expires_at', $array);
        $this->assertNotNull($array['expires_at']);
    }

    private function createMedia()
    {
        $data = app('spray-media')->uploadFile(\Illuminate\Http\UploadedFile::fake()->create('f.txt', 1, 'text/plain'));
        return app('spray-media')->createMediaItem($data);
    }
}
