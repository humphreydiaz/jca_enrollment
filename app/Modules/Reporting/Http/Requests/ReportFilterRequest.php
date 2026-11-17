<?php

namespace App\Modules\Reporting\Http\Requests;

use App\Modules\Reporting\Reports\ReportCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportFilterRequest extends FormRequest
{
    /** How the result is shown (not what it contains): kept out of filters() and export snapshots. */
    private const PRESENTATION = ['cols', 'rows', 'per_page', 'layout', 'page'];

    /** Ordering is part of what is exported, so it travels with the filters. */
    private const ORDERING = ['sort', 'direction'];

    public const PER_PAGE_OPTIONS = [25, 50, 100];

    public function authorize(): bool
    {
        return app(ReportCatalog::class)->allows($this->user(), $this->reportCode());
    }

    public function rules(): array
    {
        $catalog = app(ReportCatalog::class);
        $columnKeys = array_keys($catalog->columns($this->reportCode(), $this->user()));
        $available = [
            'school_year' => ['nullable', 'ulid', 'exists:academic_years,public_id'],
            'grade' => ['nullable', 'ulid', 'exists:grade_levels,public_id'],
            'section' => ['nullable', 'ulid', 'exists:sections,public_id'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_\-]+$/i'],
            'band' => ['nullable', Rule::in(array_keys(ReportCatalog::GRADE_BANDS))],
            'min_grade' => ['nullable', 'numeric', 'between:0,100'],
            'max_grade' => ['nullable', 'numeric', 'between:0,100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
        $allowed = array_flip($catalog->filters($this->reportCode()));

        return array_intersect_key($available, $allowed) + [
            'sort' => ['nullable', Rule::in($columnKeys)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'cols' => ['nullable', 'array', 'max:'.count($columnKeys)],
            'cols.*' => ['string', Rule::in($columnKeys)],
            'rows' => ['nullable', 'array', 'max:'.(int) config('reporting.print_row_limit')],
            // Public ULIDs, or "ULID.DOCUMENT_CODE" for requirement rows.
            'rows.*' => ['string', 'max:100', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}(\.[A-Za-z0-9_\-]{1,60})?$/'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
            'layout' => ['nullable', Rule::in(['summary', 'detail'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = [...app(ReportCatalog::class)->filters($this->reportCode()), ...self::ORDERING, ...self::PRESENTATION];
            foreach (array_keys($this->query->all()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add('filters', 'An unsupported report filter was supplied.');
                }
            }
            $min = $this->input('min_grade');
            $max = $this->input('max_grade');
            if (is_numeric($min) && is_numeric($max) && (float) $max < (float) $min) {
                $validator->errors()->add('max_grade', 'The highest grade must be at least the lowest grade.');
            }
        }];
    }

    /** What the report contains: data filters plus ordering. @return array<string, mixed> */
    public function filters(): array
    {
        return collect($this->validated())->except(self::PRESENTATION)->filter(fn ($value) => filled($value))->all();
    }

    /** @return list<string>|null */
    public function pickedColumns(): ?array
    {
        $cols = $this->validated('cols');

        return is_array($cols) && $cols !== [] ? array_values($cols) : null;
    }

    /** @return list<string> */
    public function pickedRows(): array
    {
        $rows = $this->validated('rows');

        return is_array($rows) ? array_values(array_unique($rows)) : [];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? self::PER_PAGE_OPTIONS[0]);
    }

    public function layout(): ?string
    {
        return $this->validated('layout');
    }

    private function reportCode(): string
    {
        return (string) $this->route('report');
    }
}
