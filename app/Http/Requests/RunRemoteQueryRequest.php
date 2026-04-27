<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesSqlBindings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RunRemoteQueryRequest extends FormRequest
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
            'query' => ['required', 'string', 'max:32000'],
            'bindings' => ['nullable', 'array', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->withSqlBindingsValidation($validator);
    }

    /**
     * @return array{query: string, bindings: array<string, mixed>}
     */
    public function validatedPayload(): array
    {
        /** @var array{query: string, bindings?: array<string, mixed>} $data */
        $data = $this->validated();

        return [
            'query' => $data['query'],
            'bindings' => $data['bindings'] ?? [],
        ];
    }
}
