<?php

namespace SprayMedia\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SprayMedia\Tests\TestCase;

class ControllerTest extends TestCase
{
    public function test_store_returns_resource_with_signed_url_and_can_update_and_delete(): void
    {
        Storage::fake('local');

        // Upload
        $storeResponse = $this->post('/media-items', [
            'file' => UploadedFile::fake()->create('pic.png', 5, 'image/png'),
        ]);

        $storeResponse->assertCreated();
        $storeResponse->assertJsonPath('result', 'success');
        $mediaId = $storeResponse->json('model.id');
        $signedUrl = $storeResponse->json('model.url');

        // Serve
        $serveResponse = $this->get($signedUrl);
        $serveResponse->assertOk();
        $serveResponse->assertHeader('Content-Type', 'image/png');

        // Force expiry
        $payload = ['id' => $mediaId, 'action' => 'view', 'expires_at' => now()->subMinute()->timestamp, 'metadata' => []];
        $data = base64_encode(json_encode($payload));
        $signature = hash_hmac(config('spray-media.hmac.algorithm'), $data, config('spray-media.hmac.secret'));
        $expiredResponse = $this->get('/media-items/secure?data=' . urlencode($data) . '&signature=' . $signature);
        $expiredResponse->assertStatus(403);

        // Update filename
        $updateResponse = $this->put("/media-items/{$mediaId}/update-filename", [
            'new_file_name' => 'renamed-file',
        ]);
        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('model.filename', 'renamed-file');

        // Delete
        $deleteResponse = $this->delete("/media-items/{$mediaId}");
        $deleteResponse->assertOk();
    }
}
