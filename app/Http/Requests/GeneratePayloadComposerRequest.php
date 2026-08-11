<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesSqlBindings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GeneratePayloadComposerRequest extends FormRequest
{
    use ValidatesSqlBindings;

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
            'organization_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'id'),
            ],
            'scalars' => ['nullable', 'array', 'max:64'],
            'bindings' => ['nullable', 'array', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->withSqlBindingsValidation($validator, 'bindings');

        $validator->after(function (Validator $validator): void {
            $scalars = $this->input('scalars');

            if (! is_array($scalars)) {
                return;
            }

            foreach ($scalars as $key => $value) {
                if (! is_string($key) || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key) !== 1) {
                    $validator->errors()->add(
                        'scalars',
                        'Scalar names must be identifier-style (letters, numbers, underscore).',
                    );

                    return;
                }

                if ($value !== null && ! is_scalar($value)) {
                    $validator->errors()->add(
                        'scalars.'.$key,
                        'Each scalar must be a string, number, boolean, or null.',
                    );

                    return;
                }
            }
        });
    }
}
