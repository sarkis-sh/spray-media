# Spray Media — Secure, Signed, Developer-First Media for Laravel

[![Latest Version](https://img.shields.io/packagist/v/sarkis-sh/spray-media.svg)](https://packagist.org/packages/sarkis-sh/spray-media)
[![Laravel](https://img.shields.io/badge/Laravel-10/11/12-FF2D20.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-%5E8.1-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-phpunit-green.svg)](#testing)

Spray Media gives you signed links, a hardened upload flow, and swappable building blocks so you can ship secure media delivery (view/download) quickly and confidently.

## Table of Contents
- [Why Spray Media](#why-spray-media)
- [Requirements](#requirements)
- [Install](#install)
- [Configuration Essentials](#configuration-essentials)
- [Default Routes](#default-routes)
	- [Update filename endpoint](#update-filename-endpoint)
- [Database Schema](#database-schema)
- [Quickstart (3 Steps)](#quickstart-3-steps)
- [Helpers & Resource](#helpers--resource)
- [Object Storage (S3, Cloudflare R2, MinIO)](#object-storage-s3-cloudflare-r2-minio)
	- [Serving by redirect](#serving-by-redirect)
	- [Direct uploads](#direct-uploads)
- [How It Works](#how-it-works)
- [Security Notes](#security-notes)
- [Performance](#performance)
- [Extensibility](#extensibility)
- [Translations](#translations)
- [Testing](#testing)
- [License](#license)

## Why Spray Media
- :lock: HMAC-signed URLs with optional expiration and embedded metadata.
- :inbox_tray: HTTP upload endpoint with size/MIME controls, custom rules, and automatic filename sanitizing.
- :page_facing_up: Inline or attachment responses with Cache-Control, ETag, and 304 support.
- :zap: Helpers, Resource, and Facade ready to drop into APIs or views.
- :cloud: Object storage ready: serve signed links by redirecting to the disk's own short-lived URL, and let clients upload straight to the disk (S3, Cloudflare R2, MinIO) — the bytes never pass through PHP.
- :wrench: Fully swappable components: uploader, storage, URL generator, validator, file server, repository, response adapter.
- :globe_with_meridians: Built-in translations (en/ar), configurable routes, and ready migrations.

## Requirements
- PHP >= 8.1
- Laravel 10, 11, or 12

## Install
```bash
composer require sarkis-sh/spray-media

# publish assets (config, migrations, lang)
php artisan vendor:publish --provider="SprayMedia\Providers\SprayMediaServiceProvider" --tag=config
php artisan vendor:publish --provider="SprayMedia\Providers\SprayMediaServiceProvider" --tag=migrations
php artisan vendor:publish --provider="SprayMedia\Providers\SprayMediaServiceProvider" --tag=lang

php artisan migrate
```

## Configuration Essentials
Full config lives in [src/Config/media.php](src/Config/media.php). Key options:

| Key | ENV | Default | Purpose |
| --- | --- | --- | --- |
| `disk` | `SPRAY_MEDIA_DISK` | `local` | Filesystem disk from `config/filesystems.php` |
| `base_dir` | `SPRAY_MEDIA_BASE_DIR` | `uploads` | Root folder inside the disk |
| `upload.max_kb` | `SPRAY_MEDIA_MAX_UPLOAD_KB` | `51200` | Max size (KB) |
| `upload.mimetypes` / `upload.mimes` | `SPRAY_MEDIA_MIMETYPES` / `SPRAY_MEDIA_MIMES` | `image/jpeg,image/png,application/pdf` | Allowed types |
| `route.prefix` | `SPRAY_MEDIA_ROUTE_PREFIX` | `api/media-items` | Route group prefix |
| `route.path` | `SPRAY_MEDIA_ROUTE_PATH` | `secure` | Signed serve endpoint path |
| `route.middleware_public` | `SPRAY_MEDIA_ROUTE_MIDDLEWARE_PUBLIC` | `api` | Middleware for serve endpoint |
| `route.middleware_admin` | `SPRAY_MEDIA_ROUTE_MIDDLEWARE_ADMIN` | `api` | Middleware for upload/CRUD |
| `hmac.secret` | `SPRAY_MEDIA_HMAC_SECRET` | `APP_KEY` | HMAC key |
| `hmac.algorithm` | `SPRAY_MEDIA_HMAC_ALGO` | `sha256` | HMAC algo |
| `hmac.default_expiration_minutes` | `SPRAY_MEDIA_DEFAULT_EXPIRATION_MINUTES` | `60` | Default link TTL |
| `performance.cache_control` | `SPRAY_MEDIA_CACHE_CONTROL` | `private, max-age=3600` | Cache-Control header |
| `performance.enable_etag` | `SPRAY_MEDIA_ENABLE_ETAG` | `true` | Add ETag |
| `serving.mode` | `SPRAY_MEDIA_SERVE_MODE` | `stream` | `stream` through PHP, or `redirect` to the disk's own URL |
| `serving.redirect_expiration_minutes` | `SPRAY_MEDIA_REDIRECT_EXPIRATION_MINUTES` | `5` | Lifetime of that URL |
| `direct_uploads.enabled` | `SPRAY_MEDIA_DIRECT_UPLOADS` | `false` | Turn direct uploads on |
| `direct_uploads.disk` | `SPRAY_MEDIA_DIRECT_UPLOADS_DISK` | `disk` | Disk clients upload to |
| `direct_uploads.pending_dir` | `SPRAY_MEDIA_PENDING_DIR` | `pending` | Where files wait for completion, under `base_dir` |
| `direct_uploads.url_expiration_minutes` | `SPRAY_MEDIA_UPLOAD_URL_EXPIRATION_MINUTES` | `10` | Lifetime of the upload URL |
| `direct_uploads.token_expiration_minutes` | `SPRAY_MEDIA_UPLOAD_TOKEN_EXPIRATION_MINUTES` | `60` | Time allowed to complete; age at which a pending file is pruned |

## Default Routes
Defined in [src/Routes/api.php](src/Routes/api.php) (prefix/path/middleware are configurable):

| HTTP | Path | Description | Middleware |
| --- | --- | --- | --- |
| GET | `/api/media-items/secure` | Serve signed file (view/download) | `route.middleware_public` |
| POST | `/api/media-items` | Upload file + create record | `route.middleware_admin` |
| PUT | `/api/media-items/{id}/update-filename` | Update filename only | `route.middleware_admin` |
| DELETE | `/api/media-items/{id}` | Delete file + record | `route.middleware_admin` |
| POST | `/api/media-items/direct-uploads` | Direct upload, step 1: an upload URL and a token | `route.middleware_admin` |
| POST | `/api/media-items/direct-uploads/complete` | Direct upload, step 3: check the file, create the record | `route.middleware_admin` |

The direct upload routes answer 404 while `direct_uploads.enabled` is off.

### Update filename endpoint
- Path: `PUT /api/media-items/{id}/update-filename`
- Body (JSON or form):
	- `new_file_name` (string, required) — the desired base name (extension stays the same)

Example:
```bash
curl -X PUT http://your-app.test/api/media-items/123/update-filename \
	-H "Content-Type: application/json" \
	-d '{"new_file_name": "project-spec-v2"}'
```

## Database Schema
Migration: [src/Database/Migrations/0001_01_01_000003_create_media_items_table.php](src/Database/Migrations/0001_01_01_000003_create_media_items_table.php)
- Columns: `path`, `disk`, `formatted_filename`, `filename`, `extension`, `mime_type`, `size`, timestamps.

## Quickstart (3 Steps)
1) Upload via HTTP
```bash
curl -X POST http://your-app.test/api/media-items \
	-F "file=@/path/to/file.png" \
	-F "custom_filename=My File"
```
Validation applies `max/mime/mimetypes/custom_rules` from config.

Sample success payload (see [src/Infrastructure/Http/DefaultResponseAdapter.php](src/Infrastructure/Http/DefaultResponseAdapter.php)):
```json
{
	"result": "success",
	"message": "...",
	"model": {
		"id": 1,
		"filename": "my-file",
		"formatted_filename": "my-file.png",
		"extension": "png",
		"mime_type": "image/png",
		"size": 12345,
		"url": "https://.../secure?data=...&signature=...",
		"expires_at": 1700000000
	},
	"error_list": [],
	"code": 201
}
```

2) Generate a signed URL (inline or download)
```php
use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Facades\SprayMedia;

$url = SprayMedia::generateProtectedUrl($mediaItem, MediaAction::VIEW, [
		'expiration_minutes' => 30,
		'metadata' => ['user_id' => 5],
]);

$download = media_item_generate_protected_download_url($mediaItem);
$temporary = media_item_generate_protected_temporary_url($mediaItem, 15);
```
Links carry `data` (base64 JSON) + `signature` (HMAC). Pass `expiration_minutes => null` for non-expiring links.

3) Serve the file
Hit the signed URL. The package validates signature/expiry, sets headers based on `action` (inline/attachment), and emits Cache-Control/ETag.

## Helpers & Resource
- `media_item_upload_file($file, ?$dir, ?$disk)` returns storage metadata only.
- `media_item_upload_and_create($file, $attributes = [])` uploads and creates the record.
- `media_item_generate_protected_url($media, MediaAction::VIEW, $options = [])` signed URL helper.
- `media_item_with_signed_url($media, $action, $options)` and `media_item_collection_with_signed_url(...)` wrap in Resource with `url` and `expires_at` set ([src/Http/Resources/MediaItemResource.php](src/Http/Resources/MediaItemResource.php)).
- `media_item_get_absolute_path($media)` returns filesystem path.

## Object Storage (S3, Cloudflare R2, MinIO)
Any S3-compatible disk works as `disk`: install the adapter (`composer require league/flysystem-aws-s3-v3 "^3.0"`) and define the disk. A Cloudflare R2 bucket, for example:

```php
// config/filesystems.php
'r2' => [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => 'auto',
    'bucket' => env('R2_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'), // https://<account-id>.r2.cloudflarestorage.com
    'use_path_style_endpoint' => true,
    'throw' => true,
],
```

```dotenv
SPRAY_MEDIA_DISK=r2
SPRAY_MEDIA_SERVE_MODE=redirect
SPRAY_MEDIA_DIRECT_UPLOADS=true
```

Keep the bucket private: files are only ever reached through signed links. Ordinary uploads (`POST /api/media-items`) keep working and store on the same disk, through PHP.

### Serving by redirect
With `serving.mode = redirect`, the signed link (`/api/media-items/secure`) still checks its signature and expiry, then answers `302` to a URL of the disk valid for `serving.redirect_expiration_minutes`, with the same `Content-Type` and the same inline/attachment disposition. The file goes from the bucket to the client; PHP only signs. A file on a local disk is streamed as before, whatever the mode. Image libraries and browsers follow the redirect on their own.

### Direct uploads
The client sends the file straight to the disk, in three steps.

**1. Ask for an upload URL.** The same limits as an ordinary upload apply (`upload.max_kb`, `upload.mimetypes`, `upload.mimes`, `upload.custom_rules`):
```bash
curl -X POST http://your-app.test/api/media-items/direct-uploads \
	-H "Content-Type: application/json" \
	-d '{"filename": "scan.pdf", "mime_type": "application/pdf", "size": 48213}'
```
```json
{
	"model": {
		"token": "eyJpdiI6...",
		"upload": {
			"method": "PUT",
			"url": "https://<bucket-host>/uploads/pending/2026/10/18/<uuid>.pdf?X-Amz-...",
			"headers": {"Content-Type": "application/pdf"},
			"expires_at": 1792253639
		}
	}
}
```

**2. Upload the file:** the raw bytes as the request body, with exactly those headers.
```bash
curl -X PUT "<upload.url>" -H "Content-Type: application/pdf" --data-binary @scan.pdf
```

**3. Complete.** The package checks the file's real size against the declared one, and its real type from its first bytes: object storage does not hold a signed upload to the size or type it was asked for. A file that fails is deleted. One that passes is moved into place, within the disk, and returned as an ordinary upload returns it:
```bash
curl -X POST http://your-app.test/api/media-items/direct-uploads/complete \
	-H "Content-Type: application/json" \
	-d '{"token": "eyJpdiI6..."}'
```

Nothing is written to the database before step 3: the token carries the upload, encrypted with the application key, and is good once. Files whose upload never completes wait under `base_dir/pending_dir`; schedule the cleanup:
```php
Schedule::command('spray-media:prune-pending')->hourly();
```

**Your own rules** (a storage quota, who may upload what, the folder a file goes to, the owner recorded on it) go in a policy bound as `bindings.direct_upload_policy`:
```php
use Illuminate\Validation\ValidationException;
use SprayMedia\Contracts\DirectUploadPolicyInterface;
use SprayMedia\Domain\DirectUploads\UploadDecision;
use SprayMedia\Domain\DirectUploads\UploadIntent;

class TeamUploadPolicy implements DirectUploadPolicyInterface
{
    // Step 1, before any URL is given. Throw to refuse.
    public function prepare(UploadIntent $intent): UploadDecision
    {
        $team = auth()->user()->team;

        if ($team->remainingBytes() < $intent->size) {
            throw ValidationException::withMessages(['file' => 'Out of space.']);
        }

        // The folder under base_dir, and extra (fillable) columns for the record.
        return new UploadDecision('teams/' . $team->id, ['team_id' => $team->id]);
    }

    // Step 3, with the real type and size, before the record exists. Throw to refuse: the file is deleted.
    public function confirm(UploadIntent $intent, UploadDecision $decision, string $mimeType, int $size): void
    {
        //
    }
}
```
A kind of upload may have limits of its own, checked in step 1 and again on the file in step 3 instead of `upload.max_kb` and `upload.mimetypes`: `new UploadDecision('receipts', $attributes, maxKb: 10240, mimetypes: ['image/jpeg', 'image/webp', 'application/pdf'])`. To serve such a kind from routes of your own, with their own middleware, make a manager with its own policy and validate the request yourself:
```php
$receipts = app(\SprayMedia\Application\DirectUploadManager::class, ['policy' => new ReceiptUploadPolicy()]);

$receipts->initiate($request->validated());          // step 1: filename, mime_type, size
$media = $receipts->complete($request->input('token')); // step 3
```
A token completes only through the policy that issued it: an upload checked under one set of rules cannot be finished under another.

The default policy adds no rules, and records the request's custom fields as an ordinary upload does. Uploading needs a disk that makes temporary upload URLs (an S3 disk on Laravel 11+); the URL itself comes from `bindings.upload_url_signer`, which can be swapped as well.

## How It Works
1) Upload: [LocalFileUploader](src/Infrastructure/Storage/LocalFileUploader.php) stores to configured disk/base_dir and sanitizes filenames via [FilenameSanitizer](src/Application/FilenameSanitizer.php).
2) Persist: [MediaItemManager](src/Application/MediaItemManager.php) persists metadata through the repository binding.
3) Sign: [HmacMediaItemUrlGenerator](src/Infrastructure/Url/HmacMediaItemUrlGenerator.php) builds payload, base64 encodes, signs with HMAC.
4) Validate: [HmacPayloadValidator](src/Infrastructure/Validation/HmacPayloadValidator.php) checks presence, signature (hash_equals), payload JSON, action, and expiry.
5) Serve: [LocalFileServer](src/Infrastructure/Serving/LocalFileServer.php) streams inline/attachment, applies Cache-Control/ETag, returns 304 when ETag matches.

## Security Notes
- Rotate `hmac.secret` per environment; keep it distinct from `APP_KEY` when possible.
- Choose a strong `hmac.algorithm` (sha256+). Links can be expiring or non-expiring per call.
- Filenames are sanitized to a slug to avoid header/FS issues.
- Validator rejects missing/invalid signatures, malformed payloads, wrong actions, or expired links.

## Performance
- If `expires_at` is present, Cache-Control uses the remaining lifetime automatically; otherwise uses the configured header.
- ETag is based on `id` + `updated_at` to enable 304 responses.

## Extensibility
- Swap any contract via `spray-media.bindings` in [src/Config/media.php](src/Config/media.php): repository, uploader, url_generator, payload_validator, file_server, response_adapter, direct_upload_policy, upload_url_signer.
- Override model/resource via `spray-media.model` and `spray-media.resource` to expose custom fields or relations.
- Routes, names, and middleware are fully configurable for API compatibility.

## Translations
- English and Arabic strings ship with the package; publish lang to customize.

## Testing
```bash
./vendor/bin/phpunit
```

## License
MIT
