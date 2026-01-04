<?php

namespace SprayMedia\Infrastructure\Url;

use SprayMedia\Contracts\MediaItemUrlGeneratorInterface;
use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Domain\Models\MediaItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Generates a URL with a payload signed using an HMAC hash for MediaItem resources.
 */
class HmacMediaItemUrlGenerator implements MediaItemUrlGeneratorInterface
{
    public function generate(MediaItem $media, MediaAction $action = MediaAction::VIEW, array $options = []): string
    {
        $payload = $this->buildPayload($media, $action, $options);
        $config = Config::get('spray-media.hmac');

        $data = base64_encode(json_encode($payload));
        $signature = hash_hmac($config['algorithm'], $data, $config['secret']);

        $routeUrl = URL::route(Config::get('spray-media.route.name'));

        return $routeUrl . '?data=' . urlencode($data) . '&signature=' . $signature;
    }

    /**
     * Builds the payload array to be signed.
     */
    private function buildPayload(MediaItem $media, MediaAction $action, array $options): array
    {
        $defaultExpiration = Config::get('spray-media.hmac.default_expiration_minutes');
        $expirationMinutes = $options['expiration_minutes'] ?? $defaultExpiration;

        return [
            'id' => $media->id,
            'action' => $action->value,
            'expires_at' => $expirationMinutes !== null
                ? Carbon::now()->addMinutes($expirationMinutes)->timestamp
                : null,
            'metadata' => $options['metadata'] ?? [],
        ];
    }
}
