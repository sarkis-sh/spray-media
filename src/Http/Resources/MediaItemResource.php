<?php

namespace SprayMedia\Http\Resources;

use SprayMedia\Domain\Enums\MediaAction;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class MediaItemResource extends JsonResource
{
    /**
     * Action used for generating the signed URL (view, download, etc.).
     */
    protected MediaAction $action;

    /**
     * Options forwarded to the HMAC URL generator (e.g., expiration_minutes).
     */
    protected array $urlOptions = [];

    /**
     * @param \SprayMedia\Domain\Models\MediaItem|mixed $resource MediaItem model being transformed.
     * @param MediaAction $action Defines which action the signed URL should authorize.
     * @param array<string, mixed> $urlOptions Extra options passed to `media_item_generate_protected_url`.
     */
    public function __construct($resource, MediaAction $action = MediaAction::VIEW, array $urlOptions = [])
    {
        parent::__construct($resource);
        $this->urlOptions = $urlOptions;
        $this->action = $action;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed> Includes file metadata plus a signed URL and `expires_at` timestamp.
     */
    public function toArray($request)
    {
        $expirationMinutes = Config::get('spray-media.hmac.default_expiration_minutes');

        $options = $this->urlOptions;
        if ($expirationMinutes !== null && !isset($options['expiration_minutes'])) {
            $options['expiration_minutes'] = $expirationMinutes;
        }

        return [
            'id'                 => $this->id,
            'filename'           => $this->filename,
            'formatted_filename' => $this->formatted_filename,
            'extension'          => $this->extension,
            'mime_type'          => $this->mime_type,
            'size'               => $this->size,
            'url'                => media_item_generate_protected_url(
                $this->resource,
                $this->action,
                $options
            ),
            'expires_at'         => $options['expiration_minutes'] ?? null
                ? Carbon::now()->addMinutes($options['expiration_minutes'])->timestamp
                : null,
        ];
    }
}
