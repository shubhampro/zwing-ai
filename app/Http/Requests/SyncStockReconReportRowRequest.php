<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncStockReconReportRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('stockReconSession');

        return $this->user() !== null
            && $this->user()->can(Permissions::StockReconManage)
            && $session !== null
            && $session->user_id === $this->user()->id
            && ($session->source ?? 'csv') === 'connection';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_code' => ['required', 'string', 'max:100'],
            'icode' => ['required', 'string', 'max:255'],
            'batch_no' => ['nullable', 'string', 'max:100'],
            'sprefcode' => ['required', 'string', 'max:100'],
        ];
    }

    public function siteCode(): string
    {
        return $this->string('site_code')->toString();
    }

    public function icode(): string
    {
        return $this->string('icode')->toString();
    }

    public function batchNo(): string
    {
        return $this->string('batch_no')->toString();
    }

    public function sprefcode(): string
    {
        return $this->string('sprefcode')->toString();
    }
}
