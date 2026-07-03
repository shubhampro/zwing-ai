<?php

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationApiConnectionRequest extends FormRequest
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
        /** @var Organization $organization */
        $organization = $this->route('organization');

        return [
            'third_party_api_id' => [
                'required',
                'integer',
                Rule::exists('third_party_apis', 'id')->where('is_active', true),
                Rule::unique('organization_third_party_apis', 'third_party_api_id')
                    ->where('organization_id', $organization->id),
            ],
            'base_url' => ['required', 'url', 'max:2048'],
            'auth_token' => ['required', 'string', 'max:4096'],
            'is_active' => ['boolean'],
        ];
    }
}
