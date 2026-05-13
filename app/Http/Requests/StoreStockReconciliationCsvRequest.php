<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use League\Csv\Reader;

class StoreStockReconciliationCsvRequest extends FormRequest
{
    /** Columns every reconciliation CSV must contain. */
    public const REQUIRED_COLUMNS = ['batch_no', 'barcode', 'icode', 'site_code', 'sprefcode', 'stock_point_name', 'qty'];

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
            'name' => ['required', 'string', 'max:255', 'unique:stock_recon_sessions,name'],
            'v_id' => ['required', 'integer', 'min:1'],
            'zwing_csv' => ['required', 'file', 'mimes:csv,txt', 'max:524288'],
            'erp_csv' => ['required', 'file', 'mimes:csv,txt', 'max:524288'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $zwing = $this->file('zwing_csv');
            $erp = $this->file('erp_csv');

            if ($zwing !== null) {
                $missing = $this->missingColumns($zwing->getRealPath());
                if ($missing !== []) {
                    $validator->errors()->add('zwing_csv', __(
                        'Zwing CSV is missing required columns: :cols.',
                        ['cols' => implode(', ', $missing)],
                    ));
                }
            }

            if ($erp !== null) {
                $missing = $this->missingColumns($erp->getRealPath());
                if ($missing !== []) {
                    $validator->errors()->add('erp_csv', __(
                        'ERP CSV is missing required columns: :cols.',
                        ['cols' => implode(', ', $missing)],
                    ));
                }
            }
        });
    }

    /**
     * Returns any required columns absent from the CSV header row.
     *
     * @return array<int, string>
     */
    private function missingColumns(string $path): array
    {
        try {
            $csv = Reader::createFromPath($path, 'r');
            $csv->setHeaderOffset(0);
            $headers = array_map(
                fn (string $h) => strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h) ?? $h)),
                $csv->getHeader(),
            );

            return array_values(array_diff(self::REQUIRED_COLUMNS, $headers));
        } catch (\Throwable) {
            return self::REQUIRED_COLUMNS;
        }
    }
}
