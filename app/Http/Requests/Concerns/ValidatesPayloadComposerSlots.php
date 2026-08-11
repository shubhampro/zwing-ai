<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PayloadComposerSlotShape;
use Illuminate\Validation\Validator;

trait ValidatesPayloadComposerSlots
{
    protected function withPayloadComposerSlotsValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $slots = $this->input('slots');

            if (! is_array($slots)) {
                return;
            }

            $seenKeys = [];

            foreach ($slots as $index => $slot) {
                if (! is_array($slot)) {
                    continue;
                }

                $shape = (string) ($slot['shape'] ?? '');
                $key = trim((string) ($slot['key'] ?? ''));

                if ($shape === PayloadComposerSlotShape::Array->value && $key === '') {
                    $validator->errors()->add(
                        "slots.{$index}.key",
                        'Array slots require a payload key.',
                    );

                    continue;
                }

                if ($shape === PayloadComposerSlotShape::Object->value && $key === '') {
                    continue;
                }

                if ($key === '' || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key) !== 1) {
                    $validator->errors()->add(
                        "slots.{$index}.key",
                        'Payload key must be identifier-style, or empty for object slots (merge like scalars).',
                    );

                    continue;
                }

                if (isset($seenKeys[$key])) {
                    $validator->errors()->add(
                        "slots.{$index}.key",
                        'Payload keys must be distinct.',
                    );

                    continue;
                }

                $seenKeys[$key] = true;
            }
        });
    }
}
