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

## Default Routes
Defined in [src/Routes/api.php](src/Routes/api.php) (prefix/path/middleware are configurable):

| HTTP | Path | Description | Middleware |
| --- | --- | --- | --- |
| GET | `/api/media-items/secure` | Serve signed file (view/download) | `route.middleware_public` |
| POST | `/api/media-items` | Upload file + create record | `route.middleware_admin` |
| PUT | `/api/media-items/{id}/update-filename` | Update filename only | `route.middleware_admin` |
| DELETE | `/api/media-items/{id}` | Delete file + record | `route.middleware_admin` |

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
- Swap any contract via `spray-media.bindings` in [src/Config/media.php](src/Config/media.php): repository, uploader, url_generator, payload_validator, file_server, response_adapter.
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
