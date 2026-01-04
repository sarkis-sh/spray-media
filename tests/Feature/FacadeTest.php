<?php

namespace SprayMedia\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Facades\SprayMedia;
use SprayMedia\Tests\TestCase;

class FacadeTest extends TestCase
{
    public function test_facade_generates_signed_url(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('file.txt', 1, 'text/plain');
        $data = SprayMedia::uploadFile($file);
        $media = SprayMedia::createMediaItem($data);

        $url = SprayMedia::generateProtectedUrl($media, MediaAction::DOWNLOAD);

        $this->assertStringContainsString('media-items/secure', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $decoded = json_decode(base64_decode($query['data']), true);
        $this->assertSame('download', $decoded['action']);

        $response = $this->get($url);
        $response->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
    }
}
