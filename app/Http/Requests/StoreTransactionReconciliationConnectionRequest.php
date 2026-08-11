<?php

namespace App\Http\Requests;

use App\Enums\DatabaseConnectionType;
use App\Enums\TransactionReconType;
use App\Models\Organization;
use App\Support\Permissions;
use App\Support\TransactionReconciliationQueries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransactionReconciliationConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::TransactionReconManage) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'include_zwing' => $this->boolean('include_zwing'),
            'include_erp' => $this->boolean('include_erp'),
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
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(TransactionReconType::class)],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
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

            $type = TransactionReconType::tryFrom((string) $this->input('type'));

            if ($type !== null && ! TransactionReconciliationQueries::isAvailable($type)) {
                $validator->errors()->add(
                    'type',
                    __('This transaction type is not available yet.'),
                );
            }
        });
    }
}
