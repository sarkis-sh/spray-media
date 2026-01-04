<?php

namespace SprayMedia\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SprayMedia\Domain\Models\MediaItem;
use SprayMedia\Infrastructure\Storage\LocalFileUploader;
use SprayMedia\Tests\TestCase;

class LocalFileUploaderTest extends TestCase
{
    public function test_upload_uses_base_dir_and_date_and_sanitizes_filename(): void
    {
        Carbon::setTestNow('2024-12-31');
        Config::set('spray-media.base_dir', 'media');
        Config::set('spray-media.disk', 'local');
        Storage::fake('local');

        $uploader = new LocalFileUploader();
        $file = UploadedFile::fake()->create('My File!!.png', 1, 'image/png');

        $data = $uploader->upload($file);

        $this->assertMatchesRegularExpression('/^media\/[0-9]{4}\/[0-9]{2}\//', $data['path']);
        $this->assertSame('my-file', $data['filename']);
        $this->assertSame('my-file.png', $data['formatted_filename']);
        $this->assertSame('png', $data['extension']);
        $this->assertSame('image/png', $data['mime_type']);
        $this->assertTrue(Storage::disk('local')->exists($data['path']));
    }

    public function test_delete_removes_file_from_disk(): void
    {
        Storage::fake('local');
        $media = new MediaItem([
            'path' => 'media/2024/12/sample.png',
            'disk' => 'local',
        ]);

        Storage::disk('local')->put($media->path, 'contents');

        $uploader = new LocalFileUploader();

        $this->assertTrue($uploader->delete($media));
        $this->assertFalse(Storage::disk('local')->exists($media->path));
    }

    public function test_get_absolute_path_returns_resolved_path(): void
    {
        Storage::fake('local');
        $media = new MediaItem([
            'path' => 'media/2024/12/sample.png',
            'disk' => 'local',
        ]);

        $uploader = new LocalFileUploader();
        $absolute = $uploader->getAbsolutePath($media);

        $this->assertStringEndsWith($media->path, $absolute);
    }

    public function test_delete_failure_logs_warning_and_returns_false(): void
    {
        $media = new MediaItem([
            'path' => 'media/path.png',
            'disk' => 'local',
        ]);
        $media->id = 5;

        Storage::shouldReceive('disk')->once()->with('local')->andReturnSelf();
        Storage::shouldReceive('delete')->once()->with('media/path.png')->andReturnFalse();

        Log::shouldReceive('warning')->once()->with(
            'Failed to delete media file',
            ['media_id' => 5, 'disk' => 'local', 'path' => 'media/path.png']
        );

        $uploader = new LocalFileUploader();

        $this->assertFalse($uploader->delete($media));
    }
}
