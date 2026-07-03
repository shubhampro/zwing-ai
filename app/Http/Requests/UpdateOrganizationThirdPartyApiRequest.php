<?php

namespace App\Http\Requests;

use App\Models\OrganizationThirdPartyApi;
use App\Models\ThirdPartyApi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationThirdPartyApiRequest extends FormRequest
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
        /** @var ThirdPartyApi $thirdPartyApi */
        $thirdPartyApi = $this->route('thirdPartyApi');
        /** @var OrganizationThirdPartyApi $connection */
        $connection = $this->route('organizationThirdPartyApi');

        return [
            'organization_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'id'),
                Rule::unique('organization_third_party_apis', 'organization_id')
                    ->where('third_party_api_id', $thirdPartyApi->id)
                    ->ignore($connection->id),
            ],
            'base_url' => ['required', 'url', 'max:2048'],
            'auth_token' => ['nullable', 'string', 'max:4096'],
            'is_active' => ['boolean'],
        ];
    }
}
