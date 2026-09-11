<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReportConsolidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::ReportReconManage) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? $this->input('name') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255', 'unique:report_recon_sessions,name'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Organization|null $organization */
            $organization = Organization::query()->find($this->input('organization_id'));

            if ($organization === null || blank($organization->db_name)) {
                $validator->errors()->add(
                    'organization_id',
                    __('Selected organization has no MySQL database name. Attach a Zwing vendor with db_name first.'),
                );
            }
        });
    }
}
