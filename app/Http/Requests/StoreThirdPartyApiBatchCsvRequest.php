<?php

namespace App\Http\Requests;

use App\Models\OrganizationThirdPartyApi;
use App\Services\ThirdParty\ThirdPartyApiPayloadBuilder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreThirdPartyApiBatchCsvRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:third_party_api_batches,name'],
            'organization_third_party_api_id' => [
                'required',
                'integer',
                Rule::exists('organization_third_party_apis', 'id')->where('is_active', true),
            ],
            'defaults' => ['nullable', 'array'],
            'defaults.*' => ['nullable', 'string', 'max:255'],
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:'.(512 * 1024)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'organization_third_party_api_id.exists' => 'Select an active organization API connection.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $connection = OrganizationThirdPartyApi::query()
                ->with('thirdPartyApi')
                ->whereKey($this->integer('organization_third_party_api_id'))
                ->first();

            if ($connection !== null && ! $connection->isConfigured()) {
                $validator->errors()->add('organization_third_party_api_id', 'The selected connection is missing a base URL or token.');
            }

            if (! $this->hasFile('csv') || $connection?->thirdPartyApi === null) {
                return;
            }

            $path = $this->file('csv')?->getRealPath();

            if ($path === false || $path === null) {
                return;
            }

            $handle = fopen($path, 'rb');

            if ($handle === false) {
                return;
            }

            $header = fgetcsv($handle);
            fclose($handle);

            if ($header === false) {
                $validator->errors()->add('csv', 'The CSV file is empty.');

                return;
            }

            $normalized = array_map(
                fn (?string $column) => strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', (string) $column) ?? '')),
                $header,
            );

            $requiredColumns = (new ThirdPartyApiPayloadBuilder($connection->thirdPartyApi))->requiredCsvColumns();

            foreach ($requiredColumns as $column) {
                if (! in_array($column, $normalized, true)) {
                    $validator->errors()->add('csv', "Missing required column: {$column}.");
                }
            }
        });
    }
}
