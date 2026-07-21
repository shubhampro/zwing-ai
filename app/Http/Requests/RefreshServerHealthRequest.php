<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class RefreshServerHealthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::ServerHealthManage) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
