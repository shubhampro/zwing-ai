<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFromZwingVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::OrganizationsAttachZwing) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', 'min:1', 'exists:organizations,vendor_id'],
        ];
    }
}
