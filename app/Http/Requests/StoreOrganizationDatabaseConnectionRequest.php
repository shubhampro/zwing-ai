<?php

namespace App\Http\Requests;

use App\Enums\DatabaseConnectionType;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Support\DatabaseHost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationDatabaseConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', OrganizationDatabaseConnection::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'host' => DatabaseHost::normalize($this->filled('host') ? (string) $this->input('host') : null),
            'port' => $this->filled('port') ? $this->input('port') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Organization $organization */
        $organization = $this->route('organization');

        return [
            'type' => [
                'required',
                Rule::enum(DatabaseConnectionType::class),
                Rule::unique('organization_database_connections', 'type')
                    ->where('organization_id', $organization->id),
            ],
            'database_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:4096'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'is_active' => ['boolean'],
        ];
    }
}
