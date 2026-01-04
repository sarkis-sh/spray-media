<?php

namespace SprayMedia\Tests\Unit;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SprayMedia\Application\MediaItemManager;
use SprayMedia\Domain\Models\MediaItem;
use SprayMedia\Tests\TestCase;

class MediaItemManagerTest extends TestCase
{
    public function test_create_find_update_delete_media_item(): void
    {
        Storage::fake('local');

        $manager = $this->app->make(MediaItemManager::class);
        $data = $manager->uploadFile(UploadedFile::fake()->create('note.txt', 1, 'text/plain'));
        $media = $manager->createMediaItem($data);

        $found = $manager->findMediaItemOrFail($media->id);
        $this->assertInstanceOf(MediaItem::class, $found);

        $manager->updateMediaItem($media->id, ['filename' => 'renamed']);
        $this->assertSame('renamed', $manager->findMediaItemOrFail($media->id)->filename);

        $this->assertTrue($manager->deleteMediaItem($media->id));
    }
}
