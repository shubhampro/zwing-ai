<?php

namespace App\Http\Requests;

use App\HttpMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreThirdPartyApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string', 'max:2048', 'regex:/^\//'],
            'method' => ['required', Rule::enum(HttpMethod::class)],
            'params' => ['required', 'array', 'min:1'],
            'params.*.key' => ['required', 'string', 'max:255', 'distinct'],
            'params.*.csv_column' => ['nullable', 'string', 'max:255'],
            'params.*.required' => ['boolean'],
            'params.*.default' => ['nullable', 'string', 'max:255'],
            'auth_header_name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
