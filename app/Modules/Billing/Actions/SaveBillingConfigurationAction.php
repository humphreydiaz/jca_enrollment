<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class SaveBillingConfigurationAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(Model $model, array $attributes, User $actor, string $action): Model
    {
        return DB::transaction(function () use ($model, $attributes, $actor, $action): Model {
            $model->fill($attributes)->save();
            $this->audit->record($actor, $action, $model);

            return $model;
        });
    }
}
