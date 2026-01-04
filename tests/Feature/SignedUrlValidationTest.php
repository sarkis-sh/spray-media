<?php

namespace SprayMedia\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Domain\Exceptions\ExpiredLinkException;
use SprayMedia\Domain\Exceptions\InvalidSignatureException;
use SprayMedia\Domain\Exceptions\MissingSignatureDataException;
use SprayMedia\Tests\TestCase;

class SignedUrlValidationTest extends TestCase
{
    public function test_missing_signature_data_returns_error(): void
    {
        $response = $this->get('/media-items/secure');
        $response->assertStatus(400);
        $response->assertJsonPath('result', 'error');
    }

    public function test_invalid_signature_returns_error(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('t.txt', 1, 'text/plain');
        $media = $this->uploadAndCreate($file);

        $payload = ['id' => $media->id, 'action' => MediaAction::VIEW->value, 'expires_at' => null, 'metadata' => []];
        $data = base64_encode(json_encode($payload));
        $badSignature = 'wrong';

        $response = $this->get('/media-items/secure?data=' . urlencode($data) . '&signature=' . $badSignature);
        $response->assertStatus(403);
        $response->assertJsonPath('result', 'error');
    }

    public function test_expired_link_returns_error(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('t.txt', 1, 'text/plain');
        $media = $this->uploadAndCreate($file);

        $payload = ['id' => $media->id, 'action' => MediaAction::VIEW->value, 'expires_at' => now()->subMinute()->timestamp, 'metadata' => []];
        $data = base64_encode(json_encode($payload));
        $signature = hash_hmac(config('spray-media.hmac.algorithm'), $data, config('spray-media.hmac.secret'));

        $response = $this->get('/media-items/secure?data=' . urlencode($data) . '&signature=' . $signature);
        $response->assertStatus(403);
        $response->assertJsonPath('result', 'error');
    }

    private function uploadAndCreate(UploadedFile $file)
    {
        $data = app('spray-media')->uploadFile($file);
        return app('spray-media')->createMediaItem($data);
    }
}