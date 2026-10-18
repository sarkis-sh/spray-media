<?php

namespace SprayMedia\Tests\Unit;

use DateTimeInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use SprayMedia\Domain\Exceptions\DirectUploadsUnsupportedException;
use SprayMedia\Infrastructure\DirectUploads\DiskUploadUrlSigner;
use SprayMedia\Tests\TestCase;

class DiskUploadUrlSignerTest extends TestCase
{
    public function test_it_signs_through_the_disk_and_leaves_host_to_the_client(): void
    {
        Storage::extend('signing-test', function ($app, array $config) {
            $adapter = new SigningAdapter($config['root']);

            return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
        });
        Config::set('filesystems.disks.remote', ['driver' => 'signing-test', 'root' => storage_path('framework/testing/disks/remote')]);

        $signed = (new DiskUploadUrlSigner())->sign('remote', 'uploads/pending/a.png', now()->addMinutes(10), 'image/png');

        $this->assertSame('https://bucket.example/uploads/pending/a.png?ContentType=image%2Fpng', $signed['url']);
        $this->assertSame(['Content-Type' => 'image/png', 'x-amz-acl' => 'private'], $signed['headers']);
    }

    public function test_a_disk_that_cannot_sign_uploads_is_a_configuration_error(): void
    {
        Storage::fake('local');

        $this->expectException(DirectUploadsUnsupportedException::class);

        (new DiskUploadUrlSigner())->sign('local', 'uploads/pending/a.png', now()->addMinutes(10), 'image/png');
    }
}

/** A local adapter that signs as S3 would: a URL, and the headers to send. */
class SigningAdapter extends LocalFilesystemAdapter
{
    public function temporaryUploadUrl(string $path, DateTimeInterface $expiration, array $options = []): array
    {
        return [
            'url' => 'https://bucket.example/' . $path . '?' . http_build_query($options),
            'headers' => ['Host' => ['bucket.example'], 'x-amz-acl' => ['private']],
        ];
    }
}
