<?php

namespace App\Http\Requests;

use App\Models\ThirdPartyApi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationThirdPartyApiRequest extends FormRequest
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

        return [
            'organization_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'id'),
                Rule::unique('organization_third_party_apis', 'organization_id')
                    ->where('third_party_api_id', $thirdPartyApi->id),
            ],
            'base_url' => ['required', 'url', 'max:2048'],
            'auth_token' => ['required', 'string', 'max:4096'],
            'is_active' => ['boolean'],
        ];
    }
}
