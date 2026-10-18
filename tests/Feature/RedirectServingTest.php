<?php

namespace SprayMedia\Tests\Feature;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use SprayMedia\Application\MediaItemManager;
use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Domain\Models\MediaItem;
use SprayMedia\Tests\TestCase;

/**
 * With 'serving.mode' = 'redirect', a signed link sends the client to the
 * disk's own short-lived URL instead of streaming the file through PHP --
 * for a disk that can make one. 'remote' stands in for S3 here: a driver
 * that is not 'local', with temporary URLs of its own.
 */
class RedirectServingTest extends TestCase
{
    private array $lastOptions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $root = storage_path('framework/testing/disks/remote');
        Storage::extend('remote-test', function ($app, array $config) {
            $adapter = new LocalFilesystemAdapter($config['root']);

            return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
        });
        Config::set('filesystems.disks.remote', ['driver' => 'remote-test', 'root' => $root]);

        // Laravel binds the callback to the disk, so $this would be the disk:
        // the options are caught by reference instead.
        $lastOptions = &$this->lastOptions;
        Storage::disk('remote')->buildTemporaryUrlsUsing(function (string $path, $expiration, array $options) use (&$lastOptions) {
            $lastOptions = $options;

            return 'https://files.example/' . $path . '?expires=' . $expiration->getTimestamp();
        });

        Storage::fake('local');
    }

    private function media(string $disk): MediaItem
    {
        Storage::disk($disk)->put('uploads/2026/10/scan.pdf', "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n");

        return MediaItem::create([
            'path' => 'uploads/2026/10/scan.pdf', 'disk' => $disk, 'filename' => 'scan',
            'formatted_filename' => 'scan.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'size' => 20,
        ]);
    }

    private function link(MediaItem $media, MediaAction $action = MediaAction::VIEW): string
    {
        return $this->app->make(MediaItemManager::class)->generateProtectedUrl($media, $action);
    }

    public function test_a_remote_file_is_handed_out_by_the_disk_itself(): void
    {
        Config::set('spray-media.serving.mode', 'redirect');
        Config::set('spray-media.serving.redirect_expiration_minutes', 5);
        $this->freezeTime();

        $response = $this->get($this->link($this->media('remote')));

        $response->assertRedirect('https://files.example/uploads/2026/10/scan.pdf?expires=' . now()->addMinutes(5)->getTimestamp());
        $response->assertHeader('Cache-Control', 'max-age=270, private');
        $this->assertSame('application/pdf', $this->lastOptions['ResponseContentType']);
        $this->assertSame('inline; filename=scan.pdf', $this->lastOptions['ResponseContentDisposition']);
    }

    public function test_a_download_link_stays_a_download(): void
    {
        Config::set('spray-media.serving.mode', 'redirect');

        $this->get($this->link($this->media('remote'), MediaAction::DOWNLOAD))->assertRedirect();

        $this->assertSame('attachment; filename=scan.pdf', $this->lastOptions['ResponseContentDisposition']);
    }

    public function test_streaming_stays_the_default(): void
    {
        $response = $this->get($this->link($this->media('remote')));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame([], $this->lastOptions);
    }

    public function test_a_local_file_is_streamed_even_in_redirect_mode(): void
    {
        Config::set('spray-media.serving.mode', 'redirect');

        $this->get($this->link($this->media('local')))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_missing_file_is_still_not_found(): void
    {
        Config::set('spray-media.serving.mode', 'redirect');
        $media = $this->media('remote');
        Storage::disk('remote')->delete($media->path);

        $this->get($this->link($media))->assertNotFound();
        $this->assertSame([], $this->lastOptions, 'no URL to a file that is not there');
    }
}
