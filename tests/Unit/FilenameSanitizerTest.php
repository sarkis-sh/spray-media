<?php

namespace SprayMedia\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SprayMedia\Application\FilenameSanitizer;

class FilenameSanitizerTest extends TestCase
{
    public function test_sanitizes_and_trims(): void
    {
        $this->assertSame('hello-world', FilenameSanitizer::sanitize(' Hello   World '));
    }

    public function test_removes_special_chars_and_accents(): void
    {
        $this->assertSame('cafe-resume-at-at', FilenameSanitizer::sanitize('Café résumé!@# @'));
    }

    public function test_preserves_dashes_and_underscores(): void
    {
        $this->assertSame('my-file-name', FilenameSanitizer::sanitize('my-file_name'));
    }
}
