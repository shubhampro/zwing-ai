<?php

namespace App\Http\Requests;

use App\Enums\DatabaseConnectionType;
use App\Models\Organization;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvoiceReconciliationConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::InvoiceReconManage) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'include_zwing' => $this->boolean('include_zwing'),
            'include_erp' => $this->boolean('include_erp'),
            'name' => $this->filled('name') ? $this->input('name') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Organization|null $organization */
        $organization = Organization::query()->find($this->input('organization_id'));

        return [
            'name' => ['nullable', 'string', 'max:255', 'unique:invoice_recon_sessions,name'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'include_zwing' => ['boolean'],
            'include_erp' => ['boolean'],
            'pgsql_connection_id' => [
                Rule::requiredIf(fn () => $this->boolean('include_erp')),
                'nullable',
                'integer',
                Rule::exists('organization_database_connections', 'id')
                    ->where('organization_id', $organization?->id ?? 0)
                    ->where('type', DatabaseConnectionType::Pgsql->value)
                    ->where('is_active', true),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('include_zwing') && ! $this->boolean('include_erp')) {
                $validator->errors()->add(
                    'include_zwing',
                    __('Select at least one side to pull (Zwing and/or ERP).'),
                );
            }

            /** @var Organization|null $organization */
            $organization = Organization::query()->find($this->input('organization_id'));

            if (
                ($this->boolean('include_zwing') || $this->boolean('include_erp'))
                && ($organization === null || blank($organization->db_name))
            ) {
                $validator->errors()->add(
                    'organization_id',
                    __('Selected organization has no MySQL database name. Attach a Zwing vendor with db_name first.'),
                );
            }
        });
    }
}
