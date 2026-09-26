<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreSfMbrReportRequest extends FormRequest
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
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'application_ids' => ['nullable', 'array'],
            'application_ids.*' => ['integer', Rule::exists('sf_applications', 'id')->where('is_active', true)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'starts_on' => $this->normalizeDay($this->input('starts_on')),
            'ends_on' => $this->normalizeDay($this->input('ends_on')),
            'application_ids' => array_values(array_unique(array_filter(
                array_map(intval(...), $this->input('application_ids', [])),
                fn (int $id): bool => $id > 0,
            ))),
        ]);
    }

    private function normalizeDay(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $matches) !== 1) {
            return $value;
        }

        $date = Carbon::createFromDate((int) $matches[1], (int) $matches[2], (int) $matches[3]);

        if (
            $date->year !== (int) $matches[1]
            || $date->month !== (int) $matches[2]
            || $date->day !== (int) $matches[3]
        ) {
            return $value;
        }

        return $date->toDateString();
    }
}
