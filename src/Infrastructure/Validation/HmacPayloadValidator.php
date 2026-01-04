<?php

namespace SprayMedia\Infrastructure\Validation;

use SprayMedia\Contracts\PayloadValidatorInterface;
use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Domain\Exceptions\ExpiredLinkException;
use SprayMedia\Domain\Exceptions\InvalidActionException;
use SprayMedia\Domain\Exceptions\InvalidPayloadException;
use SprayMedia\Domain\Exceptions\InvalidSignatureException;
use SprayMedia\Domain\Exceptions\MissingSignatureDataException;
use Illuminate\Support\Facades\Config;

/**
 * Class HmacPayloadValidator
 *
 * Validates an HMAC-signed request. Its responsibilities include signature
 * verification, payload decoding, and expiration checks.
 *
 * @package SprayMedia\Infrastructure\Validation
 */
class HmacPayloadValidator implements PayloadValidatorInterface
{
    public function validate(array $request): array
    {
        $payload = $this->verifySignatureAndDecode($request);

        $action = isset($payload['action']) ? MediaAction::tryFrom($payload['action']) : null;

        if (!$action) {
            throw new InvalidActionException();
        }

        $this->validateExpiration($payload);

        return $payload;
    }

    /**
     * Verifies the HMAC signature and decodes the payload from the request.
     */
    private function verifySignatureAndDecode(array $request): array
    {
        $data = $request['data'] ?? null;
        $signature = $request['signature'] ?? null;
        $config = Config::get('spray-media.hmac');

        if (!$data || !$signature) {
            throw new MissingSignatureDataException();
        }

        $expectedSignature = hash_hmac($config['algorithm'], $data, $config['secret']);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new InvalidSignatureException();
        }

        $payload = json_decode(base64_decode($data), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
            throw new InvalidPayloadException();
        }

        return $payload;
    }

    /**
     * Validates the 'expires_at' timestamp within the payload.
     */
    private function validateExpiration(array $payload): void
    {
        if (isset($payload['expires_at']) && $payload['expires_at'] < now()->timestamp) {
            throw new ExpiredLinkException();
        }
    }
}
