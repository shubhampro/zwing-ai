<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StockReconQtySumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->route('stockReconSession') !== null
            && $this->route('stockReconSession')->user_id === $this->user()->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_code' => ['required', 'string', 'max:100'],
            'icode' => ['required', 'string', 'max:255'],
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

    public function sprefcode(): string
    {
        return $this->string('sprefcode')->toString();
    }
}
