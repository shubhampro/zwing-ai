<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use League\Csv\Reader;

class StoreModelTrainingCsvRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'dataset_key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/'],
            'training_csv' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:524288'],
            'auto_train' => ['sometimes', 'boolean'],
            'target_columns' => ['required_if:auto_train,true', 'array', 'min:1'],
            'target_columns.*' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9_]+$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('training_csv');
            if ($file === null) {
                return;
            }

            $headers = $this->readHeaders($file->getRealPath(), strtolower($file->getClientOriginalExtension()));
            if ($headers === []) {
                $validator->errors()->add('training_csv', __('Could not read column headers from the uploaded file.'));

                return;
            }

            $selected = array_map('strtolower', $this->input('target_columns', []));
            $unknown = array_values(array_diff($selected, $headers));
            if ($unknown !== []) {
                $validator->errors()->add('target_columns', __(
                    'Unknown target columns: :cols.',
                    ['cols' => implode(', ', $unknown)],
                ));
            }
        });
    }

    /**
     * @return list<string>
     */
    private function readHeaders(string $path, string $extension): array
    {
        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return [];
        }

        try {
            $csv = Reader::createFromPath($path, 'r');
            $csv->setHeaderOffset(0);

            return array_map(
                fn (string $h) => strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h) ?? $h)),
                $csv->getHeader(),
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
