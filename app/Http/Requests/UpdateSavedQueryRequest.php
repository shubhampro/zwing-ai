<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesSqlBindings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSavedQueryRequest extends FormRequest
{
    use ValidatesSqlBindings;

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
            'name' => ['required', 'string', 'max:255'],
            'sql' => ['required', 'string', 'max:32000'],
            'bindings' => ['nullable', 'array', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->withSqlBindingsValidation($validator);
    }

    /**
     * @return array{name: string, sql: string, bindings: array<string, mixed>}
     */
    public function validatedPayload(): array
    {
        /** @var array{name: string, sql: string, bindings?: array<string, mixed>} $data */
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'sql' => $data['sql'],
            'bindings' => $data['bindings'] ?? [],
        ];
    }
}
