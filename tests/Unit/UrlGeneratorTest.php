<?php

namespace SprayMedia\Tests\Unit;

use Illuminate\Support\Facades\URL;
use SprayMedia\Tests\TestCase;
use SprayMedia\Domain\Enums\MediaAction;
use SprayMedia\Domain\Models\MediaItem;
use SprayMedia\Infrastructure\Url\HmacMediaItemUrlGenerator;

class UrlGeneratorTest extends TestCase
{
    public function test_generate_contains_signature_and_payload(): void
    {
        URL::forceRootUrl('http://localhost');

        $generator = new HmacMediaItemUrlGenerator();
        $media = new MediaItem([
            'filename' => 'file',
            'formatted_filename' => 'file.txt',
            'extension' => 'txt',
            'mime_type' => 'text/plain',
            'size' => 10,
        ]);
        $media->id = 1;

        $url = $generator->generate($media, MediaAction::VIEW, ['metadata' => ['a' => 'b'], 'expiration_minutes' => 10]);

        $this->assertStringContainsString('data=', $url);
        $this->assertStringContainsString('signature=', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $payload = json_decode(base64_decode($query['data']), true);
        $this->assertSame(['id'=>1,'action'=>'view','expires_at'=>$payload['expires_at'],'metadata'=>['a'=>'b']], $payload);
    }
}
