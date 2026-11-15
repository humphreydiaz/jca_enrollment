<?php

namespace App\Modules\StudentSupport\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovedSupportSummary extends Model
{
    protected $fillable = ['support_case_id', 'summary', 'approved_by', 'approved_at', 'effective_from', 'effective_until', 'superseded_by'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'effective_from' => 'date', 'effective_until' => 'date'];
    }

    public function supportCase(): BelongsTo
    {
        return $this->belongsTo(SupportCase::class);
    }

    /**
     * Approved, not superseded, and effective today: the only summaries a teacher may see.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCurrentlyEffective(Builder $query): void
    {
        $query->whereNotNull($query->qualifyColumn('approved_at'))
            ->whereNull($query->qualifyColumn('superseded_by'))
            ->where(fn (Builder $dates) => $dates->whereNull($query->qualifyColumn('effective_from'))->orWhereDate($query->qualifyColumn('effective_from'), '<=', today()))
            ->where(fn (Builder $dates) => $dates->whereNull($query->qualifyColumn('effective_until'))->orWhereDate($query->qualifyColumn('effective_until'), '>=', today()));
    }
}
