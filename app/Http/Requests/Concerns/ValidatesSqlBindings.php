<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesSqlBindings
{
    protected function withSqlBindingsValidation(Validator $validator, string $attribute = 'bindings'): void
    {
        $validator->after(function (Validator $validator) use ($attribute): void {
            $bindings = $this->input($attribute);
            if (! is_array($bindings)) {
                return;
            }

            foreach ($bindings as $key => $value) {
                if (! is_string($key) || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key) !== 1) {
                    $validator->errors()->add(
                        $attribute,
                        'Binding names must be identifier-style (letters, numbers, underscore).',
                    );

                    return;
                }

                if ($value !== null && ! is_scalar($value)) {
                    $validator->errors()->add(
                        $attribute.'.'.$key,
                        'Each binding must be a string, number, boolean, or null.',
                    );

                    return;
                }
            }
        });
    }
}
