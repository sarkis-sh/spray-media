<?php

namespace SprayMedia\Tests\Unit;

use Mockery;
use Illuminate\Http\UploadedFile;
use SprayMedia\Domain\Models\MediaItem;
use SprayMedia\Http\Services\MediaItemHttpService;
use SprayMedia\Application\MediaItemManager;
use SprayMedia\Tests\TestCase;

class MediaItemHttpServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_upload_applies_custom_filename_and_merges_payload(): void
    {
        $manager = Mockery::mock(MediaItemManager::class);
        $service = new MediaItemHttpService($manager);

        $file = UploadedFile::fake()->create('file.jpg', 1, 'image/jpeg');
        $validated = [
            'file' => $file,
            'custom_filename' => 'My File!!',
            'title' => 'Hello',
        ];

        $uploadData = [
            'extension' => 'jpg',
            'filename' => 'orig',
            'formatted_filename' => 'orig.jpg',
        ];

        $created = new MediaItem([
            'filename' => 'my-file',
            'formatted_filename' => 'my-file.jpg',
            'title' => 'Hello',
        ]);

        $manager->shouldReceive('uploadFile')->once()->with($file)->andReturn($uploadData);
        $manager->shouldReceive('createMediaItem')->once()->with([
            'extension' => 'jpg',
            'filename' => 'my-file',
            'formatted_filename' => 'my-file.jpg',
            'custom_filename' => 'My File!!',
            'title' => 'Hello',
        ])->andReturn($created);

        $result = $service->upload($validated);

        $this->assertSame($created, $result);
    }

    public function test_update_filename_sanitizes_and_refreshes_media(): void
    {
        $manager = Mockery::mock(MediaItemManager::class);
        $service = new MediaItemHttpService($manager);

        $media = Mockery::mock(MediaItem::class)->makePartial();
        $media->extension = 'png';

        $fresh = new MediaItem([
            'filename' => 'new-name',
            'formatted_filename' => 'new-name.png',
        ]);

        $media->shouldReceive('fresh')->andReturn($fresh);

        $manager->shouldReceive('findMediaItemOrFail')->once()->with(5)->andReturn($media);
        $manager->shouldReceive('updateMediaItem')->once()->with(5, [
            'formatted_filename' => 'new-name.png',
            'filename' => 'new-name',
        ]);

        $result = $service->updateFilename(5, 'New Name');

        $this->assertSame($fresh, $result);
        $this->assertSame('new-name', $result->filename);
        $this->assertSame('new-name.png', $result->formatted_filename);
    }
}
