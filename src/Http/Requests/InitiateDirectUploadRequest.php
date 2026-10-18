<?php

namespace SprayMedia\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

/**
 * Step 1 of a direct upload: the file as the client describes it, held to
 * the same limits as an ordinary upload ('upload.max_kb', 'upload.mimetypes',
 * 'upload.mimes', 'upload.custom_rules'). The type is checked again from the
 * file itself in step 3.
 */
class InitiateDirectUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) Config::get('spray-media.upload.max_kb', 51200);
        $mimetypes = Config::get('spray-media.upload.mimetypes', []);
        $mimes = array_map('strtolower', Config::get('spray-media.upload.mimes', []));

        $filenameRules = ['required', 'string', 'max:255'];
        if (!empty($mimes)) {
            $filenameRules[] = function (string $attribute, mixed $value, Closure $fail) use ($mimes) {
                if (!in_array(strtolower(pathinfo((string) $value, PATHINFO_EXTENSION)), $mimes, true)) {
                    $fail(__('validation.mimes', ['attribute' => $attribute, 'values' => implode(', ', $mimes)]));
                }
            };
        }

        $mimeTypeRules = ['required', 'string', 'max:128'];
        if (!empty($mimetypes)) {
            $mimeTypeRules[] = 'in:' . implode(',', $mimetypes);
        }

        $sizeRules = ['required', 'integer', 'min:1'];
        if ($maxKb > 0) {
            $sizeRules[] = 'max:' . ($maxKb * 1024);
        }

        return array_merge([
            'filename' => $filenameRules,
            'mime_type' => $mimeTypeRules,
            'size' => $sizeRules,
            'custom_filename' => 'nullable|string|max:255',
        ], Config::get('spray-media.upload.custom_rules', []));
    }
}
