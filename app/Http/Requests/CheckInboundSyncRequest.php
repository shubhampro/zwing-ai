<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckInboundSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'v_id' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_event_name' => ['nullable', 'string', 'max:255'],
            'client_event_unique_code' => ['nullable', 'string', 'max:255'],
        ];
    }
}
