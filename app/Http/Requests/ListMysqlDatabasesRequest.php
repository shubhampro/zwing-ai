<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMysqlDatabasesRequest extends FormRequest
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
        return [
            'connection_slug' => [
                'required',
                'string',
                'max:255',
                Rule::exists('database_connections', 'slug')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }
}
