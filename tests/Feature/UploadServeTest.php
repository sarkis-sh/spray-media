<?php

namespace SprayMedia\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SprayMedia\Application\MediaItemManager;
use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Tests\TestCase;

class UploadServeTest extends TestCase
{
    public function test_upload_generate_url_and_serve_inline_png(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('logo.png', 32, 32);

        $manager = $this->app->make(MediaItemManager::class);
        $data = $manager->uploadFile($file);
        $media = $manager->createMediaItem($data);

        $url = $manager->generateProtectedUrl($media, MediaAction::VIEW);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('Content-Disposition', 'inline; filename="'.$media->formatted_filename.'"');
        $this->assertGreaterThan(0, (int) $response->headers->get('Content-Length'));

        // Delete underlying file then expect 404 on serve.
        Storage::disk('local')->delete($media->path);
        $response404 = $this->get($url);
        $response404->assertStatus(404);
    }

    public function test_download_disposition_is_attachment(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        $manager = $this->app->make(MediaItemManager::class);
        $data = $manager->uploadFile($file);
        $media = $manager->createMediaItem($data);

        $url = $manager->generateProtectedUrl($media, MediaAction::DOWNLOAD);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="'.$media->formatted_filename.'"');
    }
}
