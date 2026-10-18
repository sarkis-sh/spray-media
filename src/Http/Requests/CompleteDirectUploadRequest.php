<?php

namespace SprayMedia\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Step 3 of a direct upload: the token step 1 gave. */
class CompleteDirectUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => 'required|string|max:8192',
        ];
    }
}
