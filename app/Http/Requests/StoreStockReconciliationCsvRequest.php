<?php

namespace App\Http\Requests;

use App\Jobs\ParseStockReconciliationLogCsv;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use League\Csv\Reader;

class StoreStockReconciliationCsvRequest extends FormRequest
{
    /** Columns every stock reconciliation CSV must contain. */
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
            'zwing_csv' => ['nullable', 'file', 'mimes:csv,txt', 'max:524288'],
            'erp_csv' => ['nullable', 'file', 'mimes:csv,txt', 'max:524288'],
            'zwing_log_csv' => ['nullable', 'file', 'mimes:csv,txt', 'max:524288'],
            'erp_log_csv' => ['nullable', 'file', 'mimes:csv,txt', 'max:524288'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $zwing = $this->file('zwing_csv');
            $erp = $this->file('erp_csv');
            $zwingLog = $this->file('zwing_log_csv');
            $erpLog = $this->file('erp_log_csv');

            if ($zwing === null && $erp === null) {
                $validator->errors()->add('zwing_csv', __('At least one stock CSV (Zwing or ERP) is required.'));
            }

            if ($zwing !== null) {
                $missing = $this->missingColumns($zwing->getRealPath(), self::REQUIRED_COLUMNS);
                if ($missing !== []) {
                    $validator->errors()->add('zwing_csv', __(
                        'Zwing CSV is missing required columns: :cols.',
                        ['cols' => implode(', ', $missing)],
                    ));
                }
            }

            if ($erp !== null) {
                $missing = $this->missingColumns($erp->getRealPath(), self::REQUIRED_COLUMNS);
                if ($missing !== []) {
                    $validator->errors()->add('erp_csv', __(
                        'ERP CSV is missing required columns: :cols.',
                        ['cols' => implode(', ', $missing)],
                    ));
                }
            }

            if ($zwingLog !== null) {
                $missing = $this->missingColumns($zwingLog->getRealPath(), ParseStockReconciliationLogCsv::LOG_COLUMNS);
                if ($missing !== []) {
                    $validator->errors()->add('zwing_log_csv', __(
                        'Zwing log CSV is missing required columns: :cols.',
                        ['cols' => implode(', ', $missing)],
                    ));
                }
            }

            if ($erpLog !== null) {
                $missing = $this->missingColumns($erpLog->getRealPath(), ParseStockReconciliationLogCsv::LOG_COLUMNS);
                if ($missing !== []) {
                    $validator->errors()->add('erp_log_csv', __(
                        'ERP log CSV is missing required columns: :cols.',
                        ['cols' => implode(', ', $missing)],
                    ));
                }
            }
        });
    }

    /**
     * Returns any required columns absent from the CSV header row.
     *
     * @param  list<string>  $requiredColumns
     * @return array<int, string>
     */
    private function missingColumns(string $path, array $requiredColumns): array
    {
        try {
            $csv = Reader::createFromPath($path, 'r');
            $csv->setHeaderOffset(0);
            $headers = array_map(
                fn (string $h) => strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h) ?? $h)),
                $csv->getHeader(),
            );

            return array_values(array_diff($requiredColumns, $headers));
        } catch (\Throwable) {
            return $requiredColumns;
        }
    }
}
