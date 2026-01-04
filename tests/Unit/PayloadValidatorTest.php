<?php

namespace SprayMedia\Tests\Unit;

use SprayMedia\Tests\TestCase;
use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Infrastructure\Validation\HmacPayloadValidator;

class PayloadValidatorTest extends TestCase
{
    public function test_validate_accepts_correct_signature(): void
    {
        $payload = ['id' => 1, 'action' => MediaAction::VIEW->value, 'expires_at' => null, 'metadata' => []];
        $data = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $data, config('spray-media.hmac.secret'));

        $validator = new HmacPayloadValidator();
        $result = $validator->validate(['data' => $data, 'signature' => $signature]);

        $this->assertSame($payload, $result);
    }
}
