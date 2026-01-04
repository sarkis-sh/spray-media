<?php

namespace SprayMedia\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SprayMedia\Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_helpers_upload_create_and_generate_urls(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('photo.jpg', 24, 24);

        $media = media_item_upload_and_create($file, ['custom' => 'yes']);

        $this->assertNotNull($media->id);
        $this->assertEquals('photo', $media->filename);

        $viewUrl = media_item_generate_protected_view_url($media);
        $downloadUrl = media_item_generate_protected_download_url($media);

        $this->assertStringContainsString('media-items/secure', $viewUrl);
        parse_str(parse_url($viewUrl, PHP_URL_QUERY), $viewQuery);
        $viewPayload = json_decode(base64_decode($viewQuery['data']), true);
        $this->assertSame('view', $viewPayload['action']);

        parse_str(parse_url($downloadUrl, PHP_URL_QUERY), $dlQuery);
        $dlPayload = json_decode(base64_decode($dlQuery['data']), true);
        $this->assertSame('download', $dlPayload['action']);

        $response = $this->get($viewUrl);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
    }
}
