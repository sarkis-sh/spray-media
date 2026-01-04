<?php

namespace SprayMedia\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate requests that update a MediaItem filename.
 */
class UpdateMediaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_file_name'    =>  'required|string|max:255'
        ];
    }
}
