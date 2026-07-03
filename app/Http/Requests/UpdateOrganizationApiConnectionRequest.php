<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\OrganizationThirdPartyApi;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationApiConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'base_url' => ['required', 'url', 'max:2048'],
            'auth_token' => ['nullable', 'string', 'max:4096'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var Organization $organization */
            $organization = $this->route('organization');
            /** @var OrganizationThirdPartyApi $connection */
            $connection = $this->route('organizationThirdPartyApi');

            if ($connection->organization_id !== $organization->id) {
                $validator->errors()->add('base_url', 'Invalid organization API connection.');
            }
        });
    }
}
