<?php

namespace App\Modules\Academics\Actions;

use App\Models\User;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SaveAcademicCatalogAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(Model $record, array $attributes, User $actor, string $action): Model
    {
        return DB::transaction(function () use ($record, $attributes, $actor, $action): Model {
            $record->fill($attributes);
            $record->save();
            $this->audit->record($actor, $action, $record);

            return $record;
        });
    }
}
