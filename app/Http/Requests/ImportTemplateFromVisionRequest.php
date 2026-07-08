<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportTemplateFromVisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKb = max(1, (int) config('template-vision.max_upload_mb', 10)) * 1024;

        return [
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', "max:{$maxKb}"],
            'refinement' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Please upload an image file.',
            'image.mimes' => 'Image must be PNG, JPEG, or WebP.',
            'image.max' => 'Image must not exceed '.config('template-vision.max_upload_mb', 10).' MB.',
        ];
    }
}
