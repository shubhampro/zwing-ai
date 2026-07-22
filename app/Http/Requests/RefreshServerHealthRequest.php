<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class RefreshServerHealthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::Admin) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
