<?php

namespace App\Http\Requests;

use App\Enums\DatabaseAccessMode;
use App\Enums\DatabaseDriver;
use App\Models\DatabaseConnection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateDatabaseConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $connection = $this->route('database_connection');

        if (! $connection instanceof DatabaseConnection) {
            return false;
        }

        return $this->user()?->can('update', $connection) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('port') === '' || $this->input('port') === null) {
            $this->merge(['port' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var DatabaseConnection $connection */
        $connection = $this->route('database_connection');

        return [
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_-]+$/', Rule::unique('database_connections', 'slug')->ignore($connection)],
            'connection_group' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_-]+$/'],
            'driver' => ['required', new Enum(DatabaseDriver::class)],
            'access_mode' => ['required', new Enum(DatabaseAccessMode::class)],
            'label' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'writes_enabled' => ['required', 'boolean'],
            'enforce_read_only_sql_guard' => ['required', 'boolean'],
            'url' => ['nullable', 'string', 'max:2048'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:5000'],
            'unix_socket' => ['nullable', 'string', 'max:255'],
            'charset' => ['nullable', 'string', 'max:64'],
            'collation' => ['nullable', 'string', 'max:64'],
            'search_path' => ['nullable', 'string', 'max:255'],
            'sslmode' => ['nullable', 'string', 'max:32'],
            'ssl_ca_path' => ['nullable', 'string', 'max:2048'],
            'mongodb_dsn' => ['nullable', 'string', 'max:2048'],
            'mongodb_authentication_database' => ['nullable', 'string', 'max:255'],
            'mongodb_read_preference' => ['nullable', 'string', 'max:64'],
            'ssh_tunnel' => ['nullable', 'string', $this->mustBeJsonString()],
            'extra_options' => ['nullable', 'string', $this->mustBeJsonString()],
        ];
    }

    protected function passedValidation(): void
    {
        $this->merge([
            'ssh_tunnel' => $this->decodedJson('ssh_tunnel'),
            'extra_options' => $this->decodedJson('extra_options'),
        ]);
    }

    /**
     * @return \Closure(string, mixed, \Closure): void
     */
    private function mustBeJsonString(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! is_string($value)) {
                $fail(__('The :attribute must be a JSON string.', ['attribute' => $attribute]));

                return;
            }

            json_decode($value);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $fail(__('The :attribute must be valid JSON.', ['attribute' => $attribute]));
            }
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodedJson(string $key): ?array
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
