<?php

namespace SprayMedia\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

/**
 * Validate upload requests for creating MediaItem records.
 */
class StoreMediaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = Config::get('spray-media.upload.max_kb', 51200);
        $mimetypes = Config::get('spray-media.upload.mimetypes', []);
        $mimes = Config::get('spray-media.upload.mimes', []);

        $fileRules = ['required', 'file', 'max:' . $maxKb];

        if (!empty($mimetypes)) {
            $fileRules[] = 'mimetypes:' . implode(',', $mimetypes);
        }

        if (!empty($mimes)) {
            $fileRules[] = 'mimes:' . implode(',', $mimes);
        }

        $baseRules = [
            'file'      => $fileRules,
            'custom_filename'  => 'nullable|string|max:255'
        ];

        $customRules = Config::get('spray-media.upload.custom_rules', []);

        return array_merge($baseRules, $customRules);
    }
}
