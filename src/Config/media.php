<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MediaItem Model
    |--------------------------------------------------------------------------
    |
    | This option allows you to override the default MediaItem model. This is
    | useful if you want to extend the model to add custom relationships
    | or functionality. The model must extend the base MediaItem model.
    |
    */
    'model' => \SprayMedia\Domain\Models\MediaItem::class,

    /*
    |--------------------------------------------------------------------------
    | Media Resource
    |--------------------------------------------------------------------------
    |
    | This option allows you to override the default Media resource. This is
    | useful if you want to expose custom fields or relationships in your
    | API responses. The resource must extend the base MediaResource.
    |
    */
    'resource' => \SprayMedia\Http\Resources\MediaItemResource::class,

    /*
    |--------------------------------------------------------------------------
    | Bindings (overridable contracts)
    |--------------------------------------------------------------------------
    |
    | Swap these bindings to customize storage, serving, URL generation,
    | validation, and response shaping without touching the service provider.
    | Each value must be a class-string implementing the corresponding
    | contract.
    |
    */
    'bindings' => [
        'repository' => \SprayMedia\Infrastructure\Persistence\EloquentMediaItemRepository::class,
        'uploader' => \SprayMedia\Infrastructure\Storage\LocalFileUploader::class,
        'url_generator' => \SprayMedia\Infrastructure\Url\HmacMediaItemUrlGenerator::class,
        'payload_validator' => \SprayMedia\Infrastructure\Validation\HmacPayloadValidator::class,
        'file_server' => \SprayMedia\Infrastructure\Serving\LocalFileServer::class,
        'response_adapter' => \SprayMedia\Infrastructure\Http\DefaultResponseAdapter::class,

        // Direct uploads (see 'direct_uploads' below).
        'direct_upload_policy' => \SprayMedia\Infrastructure\DirectUploads\AllowAllDirectUploadPolicy::class,
        'upload_url_signer' => \SprayMedia\Infrastructure\DirectUploads\DiskUploadUrlSigner::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    |
    | This option defines the default storage disk where all uploaded media
    | will be physically stored. This disk name must correspond to one of
    | the disks defined in your `config/filesystems.php` file.
    |
    | Default: 'public'
    |
    */
    'disk' => env('SPRAY_MEDIA_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Base Directory
    |--------------------------------------------------------------------------
    |
    | Defines a root directory within the selected disk where all media files
    | will be placed. This helps in organizing files and keeping them
    | separate from other application-uploaded files.
    | Example: 'uploads' on the 'public' disk will store files in 'storage/app/public/uploads'.
    |
    | Default: 'uploads'
    |
    */
    'base_dir' => env('SPRAY_MEDIA_BASE_DIR', 'uploads'),

    /*
    |--------------------------------------------------------------------------
    | Upload Validation
    |--------------------------------------------------------------------------
    |
    | These options drive the request validation rules for uploads. Keep them
    | configurable so environments with stricter needs (e.g. production) can
    | dial limits without code changes.
    |
    */
    'upload' => [
        // Maximum upload size in kilobytes (Laravel's `max` rule uses KB units).
        'max_kb' => (int) env('SPRAY_MEDIA_MAX_UPLOAD_KB', 51200), // 50 MB default

        // List of allowed MIME types. Leave empty to skip MIME filtering.
        'mimetypes' => array_filter(array_map('trim', explode(',', env('SPRAY_MEDIA_MIMETYPES', 'image/jpeg,image/png,application/pdf')))),

        // Alternative extension whitelist; leave empty to skip. Use either this or mimetypes.
        'mimes' => array_filter(array_map('trim', explode(',', env('SPRAY_MEDIA_MIMES', '')))),

        /**
         * Extra validation rules.
         * This allows users to validate their own custom fields.
         */
        'custom_rules' => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Serving Route Configuration
    |--------------------------------------------------------------------------
    |
    | These settings define the endpoint that serves media files. This allows
    | the library to handle access control, URL signing validation, and
    | serving the file content for both viewing and downloading.
    |
    */
    'route' => [
        /**
         * The main URL prefix for grouping all media-related routes.
         * Example: 'media' will result in URLs starting with /media/...
         */
        'prefix' => env('SPRAY_MEDIA_ROUTE_PREFIX', 'api/media-items'),

        /**
         * The specific path segment for the secure file serving endpoint.
         */
        'path' => env('SPRAY_MEDIA_ROUTE_PATH', 'secure'),

        /**
         * The registered name for the serving route. Useful for URL generation.
         */
        'name' => env('SPRAY_MEDIA_ROUTE_NAME', 'media-items.secure'),

        /**
         * Middleware stack for the public signed-serving endpoint.
         * Example env: "api" or "api,throttle:60,1".
         */
        'middleware_public' => array_filter(array_map('trim', explode(',', env('SPRAY_MEDIA_ROUTE_MIDDLEWARE_PUBLIC', 'api')))),

        /**
         * Middleware stack for CRUD/admin endpoints (upload/update/delete).
         * Example env: "api,auth:sanctum,throttle:20,1".
         */
        'middleware_admin' => array_filter(array_map('trim', explode(',', env('SPRAY_MEDIA_ROUTE_MIDDLEWARE_ADMIN', 'api')))),
    ],

    /*
    |--------------------------------------------------------------------------
    | HMAC Security Settings
    |--------------------------------------------------------------------------
    |
    | These settings are used for signing and validating temporary URLs.
    | HMAC (Hash-based Message Authentication Code) ensures that the URL
    | has not been tampered with and has not expired.
    |
    */
    'hmac' => [
        /**
         * A secure, random string used as a secret key for signing URLs.
         * IMPORTANT: This should be a strong, unique key. It defaults to APP_KEY,
         * but it's recommended to generate a dedicated key for this package.
         */
        'secret' => env('SPRAY_MEDIA_HMAC_SECRET', env('APP_KEY')),

        /**
         * The hashing algorithm to use for the signature.
         * You can see available algorithms by running `hash_hmac_algos()`.
         */
        'algorithm' => env('SPRAY_MEDIA_HMAC_ALGO', 'sha256'),

        /**
         * Default expiration for generated links (in minutes). Set to null to disable
         * automatic expiry and allow non-expiring links.
         */
        'default_expiration_minutes' => env('SPRAY_MEDIA_DEFAULT_EXPIRATION_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance & Advanced Options
    |--------------------------------------------------------------------------
    |
    | Fine-tune the performance of media delivery.
    |
    */
    'performance' => [
        /**
         * The 'Cache-Control' header value for served media. This instructs
         * browsers on how to cache the files.
         */
        'cache_control' => env('SPRAY_MEDIA_CACHE_CONTROL', 'private, max-age=3600'),

        /**
         * The 'ETag' header helps browsers determine if a file has changed
         * since the last request, saving bandwidth by responding with a "304".
         */
        'enable_etag' => env('SPRAY_MEDIA_ENABLE_ETAG', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Serving
    |--------------------------------------------------------------------------
    |
    | How a signed link hands out its file once the signature checks out.
    |
    | 'stream'   PHP reads the file from the disk and sends it (the default,
    |            and the only way for a local disk).
    | 'redirect' the client is sent to a short-lived URL of the disk itself
    |            (S3, Cloudflare R2, MinIO, ...), so the bytes never pass
    |            through PHP. A disk that cannot make temporary URLs is
    |            streamed all the same.
    |
    */
    'serving' => [
        'mode' => env('SPRAY_MEDIA_SERVE_MODE', 'stream'),

        // How long the disk's own URL is valid for, in minutes.
        'redirect_expiration_minutes' => (int) env('SPRAY_MEDIA_REDIRECT_EXPIRATION_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Direct Uploads
    |--------------------------------------------------------------------------
    |
    | Lets a client send a file straight to the disk (S3, Cloudflare R2,
    | MinIO, ...) instead of through PHP, in three steps:
    |
    |   1. POST {prefix}/direct-uploads             name, type and size -> a
    |      short-lived upload URL and a token. Checked against 'upload' above
    |      and against the direct upload policy before any URL is given.
    |   2. PUT the file to that URL.
    |   3. POST {prefix}/direct-uploads/complete    the token -> the media
    |      item, as an ordinary upload returns it. The file's real size and
    |      type (read from its first bytes) are checked here: object storage
    |      does not hold a signed upload to the size or type it was asked for.
    |
    | Nothing is written to the database before step 3: the token carries the
    | upload, encrypted. The file waits under base_dir/pending_dir, and is
    | moved to its place, within the disk, once it passes. Uploads that never
    | complete are cleared by `php artisan spray-media:prune-pending`.
    |
    | The disk has to make temporary upload URLs (an S3 disk does).
    |
    */
    'direct_uploads' => [
        'enabled' => (bool) env('SPRAY_MEDIA_DIRECT_UPLOADS', false),

        // The disk files are uploaded to; null uses 'disk' above.
        'disk' => env('SPRAY_MEDIA_DIRECT_UPLOADS_DISK'),

        // Where files wait for step 3, under base_dir.
        'pending_dir' => env('SPRAY_MEDIA_PENDING_DIR', 'pending'),

        // How long the upload URL of step 1 accepts the file, in minutes.
        'url_expiration_minutes' => (int) env('SPRAY_MEDIA_UPLOAD_URL_EXPIRATION_MINUTES', 10),

        // How long after step 1 step 3 is accepted, in minutes; also the age
        // after which prune-pending clears a file that never completed.
        'token_expiration_minutes' => (int) env('SPRAY_MEDIA_UPLOAD_TOKEN_EXPIRATION_MINUTES', 60),

        // How much of the file is read to tell its real type.
        'sniff_bytes' => 4096,
    ],

];
