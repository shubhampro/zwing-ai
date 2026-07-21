<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Support\Permissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role as SpatieRole;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::RolesManage) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var SpatieRole $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique(config('permission.table_names.roles'), 'name')->ignore($role->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var SpatieRole $role */
                $role = $this->route('role');
                $name = $this->string('name')->toString();
                $permissions = $this->input('permissions', []);

                if (! is_array($permissions)) {
                    $permissions = [];
                }

                if (Role::isSystem($role->name) && $name !== $role->name) {
                    $validator->errors()->add('name', __('System roles cannot be renamed.'));
                }

                if ($role->name === Role::Admin->value) {
                    foreach ([Permissions::UsersManage, Permissions::RolesManage] as $required) {
                        if (! in_array($required, $permissions, true)) {
                            $validator->errors()->add(
                                'permissions',
                                __('The admin role must keep :permission.', ['permission' => $required]),
                            );
                        }
                    }
                }
            },
        ];
    }
}
