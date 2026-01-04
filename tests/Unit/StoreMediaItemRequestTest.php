<?php

namespace SprayMedia\Tests\Unit;

use Illuminate\Support\Facades\Config;
use SprayMedia\Http\Requests\StoreMediaItemRequest;
use SprayMedia\Tests\TestCase;

class StoreMediaItemRequestTest extends TestCase
{
    public function test_rules_include_configured_limits_and_mimes(): void
    {
        Config::set('spray-media.upload.max_kb', 123);
        Config::set('spray-media.upload.mimetypes', ['image/png', 'application/pdf']);
        Config::set('spray-media.upload.mimes', ['png', 'pdf']);
        Config::set('spray-media.upload.custom_rules', ['extra' => 'required|string']);

        $request = new StoreMediaItemRequest();
        $rules = $request->rules();

        $this->assertContains('required', $rules['file']);
        $this->assertContains('file', $rules['file']);
        $this->assertContains('max:123', $rules['file']);
        $this->assertContains('mimetypes:image/png,application/pdf', $rules['file']);
        $this->assertContains('mimes:png,pdf', $rules['file']);
        $this->assertArrayHasKey('extra', $rules);
        $this->assertEquals('required|string', $rules['extra']);
    }
}
