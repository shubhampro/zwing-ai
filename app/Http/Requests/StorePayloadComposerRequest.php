<?php

namespace App\Http\Requests;

use App\Enums\PayloadComposerSlotShape;
use App\Http\Requests\Concerns\ValidatesPayloadComposerSlots;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePayloadComposerRequest extends FormRequest
{
    use ValidatesPayloadComposerSlots;

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
            'description' => ['nullable', 'string', 'max:2000'],
            'scalars' => ['nullable', 'array', 'max:64'],
            'scalars.*.key' => ['required', 'string', 'max:64', 'distinct', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'scalars.*.required' => ['boolean'],
            'scalars.*.default' => ['nullable', 'string', 'max:1000'],
            'slots' => ['required', 'array', 'min:1', 'max:20'],
            'slots.*.key' => ['nullable', 'string', 'max:64'],
            'slots.*.saved_sql_query_id' => [
                'required',
                'integer',
                Rule::exists('saved_sql_queries', 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
            'slots.*.shape' => ['required', Rule::enum(PayloadComposerSlotShape::class)],
            'slots.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->withPayloadComposerSlotsValidation($validator);
    }
}
