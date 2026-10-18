<?php

namespace SprayMedia\Tests\Feature;

use DateTimeInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use SprayMedia\Application\DirectUploadManager;
use SprayMedia\Contracts\DirectUploadPolicyInterface;
use SprayMedia\Contracts\UploadUrlSignerInterface;
use SprayMedia\Domain\DirectUploads\UploadDecision;
use SprayMedia\Domain\DirectUploads\UploadIntent;
use SprayMedia\Domain\Exceptions\UploadRejectedException;
use SprayMedia\Domain\Models\MediaItem;
use SprayMedia\Tests\TestCase;

/**
 * A client sends the file straight to the disk: step 1 gives an upload URL
 * and a token, step 2 (the upload) is played here by writing to the fake
 * disk, step 3 checks the file and makes it a media item.
 */
class DirectUploadTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private FakeUploadUrlSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Config::set('spray-media.direct_uploads.enabled', true);

        $this->signer = new FakeUploadUrlSigner();
        $this->app->instance(UploadUrlSignerInterface::class, $this->signer);
    }

    private function png(): string
    {
        return base64_decode(self::PNG);
    }

    private function initiate(array $data = [])
    {
        return $this->postJson('/media-items/direct-uploads', $data + [
            'filename' => 'X-Ray Front.PNG',
            'mime_type' => 'image/png',
            'size' => strlen($this->png()),
        ]);
    }

    /** Step 2, as the client would: the bytes, at the path the URL was signed for. */
    private function upload(string $bytes): void
    {
        Storage::disk('local')->put($this->signer->lastPath, $bytes);
    }

    private function complete(string $token)
    {
        return $this->postJson('/media-items/direct-uploads/complete', ['token' => $token]);
    }

    /* ------------------------------ step 1 ------------------------------ */

    public function test_nothing_is_offered_while_direct_uploads_are_off(): void
    {
        Config::set('spray-media.direct_uploads.enabled', false);

        $this->initiate()->assertNotFound();
        $this->complete('anything')->assertNotFound();
    }

    public function test_step_one_gives_a_url_and_a_token_and_writes_nothing(): void
    {
        $response = $this->initiate()->assertOk();

        $this->assertSame('PUT', $response->json('model.upload.method'));
        $this->assertSame('https://uploads.test/' . $this->signer->lastPath, $response->json('model.upload.url'));
        $this->assertSame(['Content-Type' => 'image/png'], $response->json('model.upload.headers'));
        $this->assertIsInt($response->json('model.upload.expires_at'));
        $this->assertNotEmpty($response->json('model.token'));

        $this->assertStringStartsWith('uploads/pending/', $this->signer->lastPath);
        $this->assertStringEndsWith('.png', $this->signer->lastPath);
        $this->assertSame(0, MediaItem::count(), 'nothing is written before step 3');
    }

    public function test_step_one_holds_the_file_to_the_upload_limits(): void
    {
        Config::set('spray-media.upload.max_kb', 1);

        $this->initiate(['size' => 2048])->assertStatus(400)->assertJsonPath('error_list.size', fn ($message) => $message !== '');
        $this->initiate(['mime_type' => 'text/html'])->assertStatus(400);
        $this->initiate(['filename' => null])->assertStatus(400);

        Config::set('spray-media.upload.mimes', ['jpg']);
        $this->initiate()->assertStatus(400)->assertJsonPath('error_list.filename', fn ($message) => $message !== '');

        $this->assertNull($this->signer->lastPath, 'no URL for a file that is not allowed');
    }

    /* ------------------------------ step 3 ------------------------------ */

    public function test_step_three_makes_the_media_item_from_the_file_itself(): void
    {
        $token = $this->initiate()->json('model.token');
        $pending = $this->signer->lastPath;
        $this->upload($this->png());

        $response = $this->complete($token)->assertCreated();

        $media = MediaItem::sole();
        $this->assertSame($media->id, $response->json('model.id'));
        $this->assertSame('x-ray-front', $media->filename);
        $this->assertSame('x-ray-front.png', $media->formatted_filename);
        $this->assertSame('png', $media->extension);
        $this->assertSame('image/png', $media->mime_type);
        $this->assertSame(strlen($this->png()), $media->size);
        $this->assertSame('local', $media->disk);
        $this->assertMatchesRegularExpression('#^uploads/\d{4}/\d{2}/[A-Za-z0-9]{40}\.png$#', $media->path);

        Storage::disk('local')->assertExists($media->path);
        Storage::disk('local')->assertMissing($pending);
        $this->assertNotEmpty($response->json('model.url'), 'a signed link, as an ordinary upload returns');
    }

    public function test_a_custom_name_is_kept_as_for_an_ordinary_upload(): void
    {
        $token = $this->initiate(['custom_filename' => 'Panoramic 2026'])->json('model.token');
        $this->upload($this->png());

        $this->complete($token)->assertCreated();

        $this->assertSame('panoramic-2026.png', MediaItem::sole()->formatted_filename);
    }

    public function test_a_file_of_another_size_is_refused_and_deleted(): void
    {
        $token = $this->initiate()->json('model.token');
        $pending = $this->signer->lastPath;
        $this->upload($this->png() . str_repeat('x', 500));

        $this->complete($token)->assertStatus(422)
            ->assertJsonPath('message', __('spray-media::messages.upload_size_mismatch'));

        Storage::disk('local')->assertMissing($pending);
        $this->assertSame(0, MediaItem::count());
    }

    public function test_the_type_is_read_from_the_file_not_taken_from_the_client(): void
    {
        $html = '<html><script>alert(1)</script></html>';
        $token = $this->initiate(['size' => strlen($html)])->json('model.token');
        $pending = $this->signer->lastPath;
        $this->upload($html);

        $this->complete($token)->assertStatus(422)
            ->assertJsonPath('message', __('spray-media::messages.upload_type_not_allowed'));

        Storage::disk('local')->assertMissing($pending);
        $this->assertSame(0, MediaItem::count());
    }

    public function test_a_token_is_good_once_and_only_as_issued(): void
    {
        $token = $this->initiate()->json('model.token');

        $this->complete($token)->assertStatus(422)
            ->assertJsonPath('message', __('spray-media::messages.upload_not_received'));

        $this->upload($this->png());
        $middle = intdiv(strlen($token), 2);
        $tampered = substr_replace($token, $token[$middle] === 'A' ? 'B' : 'A', $middle, 1);
        $this->complete($tampered)->assertStatus(400)
            ->assertJsonPath('message', __('spray-media::messages.invalid_upload_token'));

        $this->complete($token)->assertCreated();
        $this->complete($token)->assertStatus(422)
            ->assertJsonPath('message', __('spray-media::messages.upload_not_received'));

        $this->assertSame(1, MediaItem::count());
    }

    public function test_a_late_completion_is_refused_and_its_file_deleted(): void
    {
        $token = $this->initiate()->json('model.token');
        $pending = $this->signer->lastPath;
        $this->upload($this->png());

        $this->travel(61)->minutes();

        $this->complete($token)->assertStatus(410);
        Storage::disk('local')->assertMissing($pending);
    }

    /* ------------------------------ the application's policy ------------------------------ */

    public function test_the_policy_places_the_file_and_records_its_owner(): void
    {
        Schema::table('media_items', fn (Blueprint $table) => $table->string('owner')->nullable());
        Config::set('spray-media.model', OwnedMediaItem::class);
        $this->app->bind(DirectUploadPolicyInterface::class, TeamPolicy::class);

        $token = $this->initiate()->json('model.token');
        $this->upload($this->png());
        $this->complete($token)->assertCreated();

        $media = OwnedMediaItem::sole();
        $this->assertSame('team-7', $media->owner);
        $this->assertStringStartsWith('uploads/teams/7/', $media->path);
    }

    public function test_the_policy_may_refuse_before_and_after_the_upload(): void
    {
        $this->app->bind(DirectUploadPolicyInterface::class, TeamPolicy::class);

        TeamPolicy::$refuseAt = 'prepare';
        $this->initiate()->assertStatus(400)->assertJsonPath('error_list.file', 'Out of space.');
        $this->assertNull($this->signer->lastPath);

        TeamPolicy::$refuseAt = 'confirm';
        $token = $this->initiate()->json('model.token');
        $pending = $this->signer->lastPath;
        $this->upload($this->png());

        $this->complete($token)->assertStatus(400)->assertJsonPath('error_list.file', 'Out of space.');
        Storage::disk('local')->assertMissing($pending);
        $this->assertSame(0, MediaItem::count());

        TeamPolicy::$refuseAt = null;
    }

    public function test_a_policy_may_give_a_kind_of_upload_its_own_limits(): void
    {
        // Pictures of receipts: WebP allowed here, though not in 'upload.mimetypes', and 1 KB at most.
        $receipts = $this->app->make(DirectUploadManager::class, ['policy' => new ReceiptPolicy()]);
        $webp = 'RIFF' . pack('V', 30) . 'WEBPVP8 ' . pack('V', 18) . str_repeat("\0", 18);

        try {
            $receipts->initiate(['filename' => 'slip.png', 'mime_type' => 'image/png', 'size' => 100]);
            $this->fail('a type the policy does not allow is refused before any URL');
        } catch (UploadRejectedException $e) {
            $this->assertSame(UploadRejectedException::TYPE_NOT_ALLOWED, $e->getReason());
        }

        try {
            $receipts->initiate(['filename' => 'slip.webp', 'mime_type' => 'image/webp', 'size' => 2048]);
            $this->fail('a size over the policy\'s limit is refused before any URL');
        } catch (UploadRejectedException $e) {
            $this->assertSame(UploadRejectedException::TOO_LARGE, $e->getReason());
        }

        $this->assertNull($this->signer->lastPath);

        $token = $receipts->initiate(['filename' => 'slip.webp', 'mime_type' => 'image/webp', 'size' => strlen($webp)])['token'];
        $this->upload($webp);

        $this->assertSame('image/webp', $receipts->complete($token)->mime_type);
    }

    public function test_a_token_completes_only_through_the_policy_that_issued_it(): void
    {
        $receipts = $this->app->make(DirectUploadManager::class, ['policy' => new ReceiptPolicy()]);
        $webp = 'RIFF' . pack('V', 30) . 'WEBPVP8 ' . pack('V', 18) . str_repeat("\0", 18);
        $token = $receipts->initiate(['filename' => 'slip.webp', 'mime_type' => 'image/webp', 'size' => strlen($webp)])['token'];
        $this->upload($webp);

        // The general endpoint, under the default policy.
        $this->complete($token)->assertStatus(400)
            ->assertJsonPath('message', __('spray-media::messages.invalid_upload_token'));

        $this->assertSame(0, MediaItem::count());
        $receipts->complete($token);
        $this->assertSame(1, MediaItem::count());
    }

    /* ------------------------------ uploads never completed ------------------------------ */

    public function test_prune_pending_deletes_only_what_waited_too_long(): void
    {
        $disk = Storage::disk('local');
        $disk->put('uploads/pending/2026/10/01/old.png', 'x');
        $disk->put('uploads/pending/2026/10/18/new.png', 'x');
        $disk->put('uploads/2026/10/kept.png', 'x');
        touch($disk->path('uploads/pending/2026/10/01/old.png'), time() - 2 * 3600);

        $this->artisan('spray-media:prune-pending')
            ->expectsOutput('Deleted 1 pending upload(s).')
            ->assertSuccessful();

        $disk->assertMissing('uploads/pending/2026/10/01/old.png');
        $disk->assertExists('uploads/pending/2026/10/18/new.png');
        $disk->assertExists('uploads/2026/10/kept.png');
    }
}

/** Signs nothing: records the path, as a disk's temporaryUploadUrl() would be asked. */
class FakeUploadUrlSigner implements UploadUrlSignerInterface
{
    public ?string $lastPath = null;

    public function sign(string $disk, string $path, DateTimeInterface $expiresAt, string $mimeType): array
    {
        $this->lastPath = $path;

        return ['url' => 'https://uploads.test/' . $path, 'headers' => ['Content-Type' => $mimeType]];
    }
}

class OwnedMediaItem extends MediaItem
{
    protected $fillable = ['path', 'disk', 'filename', 'formatted_filename', 'extension', 'mime_type', 'size', 'owner'];
}

/** A kind of upload with limits of its own. */
class ReceiptPolicy implements DirectUploadPolicyInterface
{
    public function prepare(UploadIntent $intent): UploadDecision
    {
        return new UploadDecision('receipts', [], 1, ['image/webp', 'application/pdf']);
    }

    public function confirm(UploadIntent $intent, UploadDecision $decision, string $mimeType, int $size): void
    {
        //
    }
}

/** An application's rules: a team's own folder, its name on the file, and a quota that may say no. */
class TeamPolicy implements DirectUploadPolicyInterface
{
    public static ?string $refuseAt = null;

    public function prepare(UploadIntent $intent): UploadDecision
    {
        if (self::$refuseAt === 'prepare') {
            throw ValidationException::withMessages(['file' => 'Out of space.']);
        }

        return new UploadDecision('teams/7', ['owner' => 'team-7']);
    }

    public function confirm(UploadIntent $intent, UploadDecision $decision, string $mimeType, int $size): void
    {
        if (self::$refuseAt === 'confirm') {
            throw ValidationException::withMessages(['file' => 'Out of space.']);
        }
    }
}
