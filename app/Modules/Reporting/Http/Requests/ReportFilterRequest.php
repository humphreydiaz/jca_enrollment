<?php

namespace App\Modules\Reporting\Http\Requests;

use App\Modules\Reporting\Reports\ReportCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(ReportCatalog::class)->allows($this->user(), (string) $this->route('report'));
    }

    public function rules(): array
    {
        $available = [
            'school_year' => ['nullable', 'ulid', 'exists:academic_years,public_id'],
            'grade' => ['nullable', 'ulid', 'exists:grade_levels,public_id'],
            'section' => ['nullable', 'ulid', 'exists:sections,public_id'],
            'status' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_\-]+$/i'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
        $allowed = array_flip(app(ReportCatalog::class)->filters((string) $this->route('report')));

        return array_intersect_key($available, $allowed) + ['page' => ['nullable', 'integer', 'min:1', 'max:100000']];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = array_merge(app(ReportCatalog::class)->filters((string) $this->route('report')), ['page']);
            foreach (array_keys($this->query->all()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add('filters', 'An unsupported report filter was supplied.');
                }
            }
        }];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return collect($this->validated())->except('page')->filter(fn ($value) => filled($value))->all();
    }
}
