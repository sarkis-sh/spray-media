<?php

namespace SprayMedia\Application;

use Illuminate\Support\Str;

/**
 * Utility to normalize filenames to a safe, header-friendly format.
 */
class FilenameSanitizer
{
    /**
     * Remove control characters and dangerous quotes, then slug.
     */
    public static function sanitize(string $name): string
    {
        $clean = preg_replace("/[\r\n\t\"';]+/", '', $name);
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', '', $clean);
        $clean = Str::slug($clean ?: 'file');

        return $clean !== '' ? $clean : 'file';
    }
}
