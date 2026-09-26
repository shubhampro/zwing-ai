<?php

namespace App\Http\Requests;

use App\Models\SfMbrReport;
use App\Support\Permissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SfMbrOverallSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::SfMbrView) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'application_ids' => ['nullable', 'array'],
            'application_ids.*' => ['integer', Rule::exists('sf_applications', 'id')],
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['integer', Rule::exists('sf_modules', 'id')],
            'include_no_module' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $report = $this->route('sfMbrReport');
                $applicationIds = $this->applicationIds();

                if (! $report instanceof SfMbrReport || $applicationIds === null) {
                    return;
                }

                $allowed = $report->applications()
                    ->pluck('sf_applications.id')
                    ->map(fn (mixed $id): int => (int) $id);

                if ($allowed->isEmpty()) {
                    return;
                }

                foreach ($applicationIds as $applicationId) {
                    if (! $allowed->contains($applicationId)) {
                        $validator->errors()->add('application_ids', __('The selected application is not in this report.'));

                        return;
                    }
                }
            },
        ];
    }

    /**
     * @return list<int>|null
     */
    public function applicationIds(): ?array
    {
        if (! $this->exists('application_ids')) {
            return null;
        }

        return array_values(array_map(intval(...), $this->input('application_ids', [])));
    }

    /**
     * @return list<int>|null
     */
    public function moduleIds(): ?array
    {
        if ($this->exists('module_ids')) {
            return array_values(array_map(intval(...), $this->input('module_ids', [])));
        }

        if ($this->exists('include_no_module')) {
            return [];
        }

        return null;
    }

    public function includeNoModule(): ?bool
    {
        if (! $this->exists('include_no_module')) {
            return null;
        }

        return $this->boolean('include_no_module');
    }

    protected function prepareForValidation(): void
    {
        $applicationIds = $this->query('application_ids', $this->input('application_ids'));
        $moduleIds = $this->query('module_ids', $this->input('module_ids'));

        $payload = [];

        if ($this->exists('application_ids') || $this->query->has('application_ids')) {
            $payload['application_ids'] = array_values(array_filter(
                array_map(intval(...), is_array($applicationIds) ? $applicationIds : []),
                fn (int $id): bool => $id > 0,
            ));
        }

        if ($this->exists('module_ids') || $this->query->has('module_ids')) {
            $payload['module_ids'] = array_values(array_filter(
                array_map(intval(...), is_array($moduleIds) ? $moduleIds : []),
                fn (int $id): bool => $id > 0,
            ));
        }

        if ($this->exists('include_no_module') || $this->query->has('include_no_module')) {
            $payload['include_no_module'] = $this->boolean('include_no_module');
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
