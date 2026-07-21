<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::InvitesManage) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email', 'max:255'],
            'role' => [
                'required',
                'string',
                'max:255',
                Rule::exists(config('permission.table_names.roles'), 'name'),
            ],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
