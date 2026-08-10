<?php

namespace App\Modules\Requirements\Console;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Requirements\Actions\PreparePromotedEnrollmentRequirementsAction;
use App\Modules\SectionAssignment\Models\PromotionRecommendation;
use Illuminate\Console\Command;

class CarryForwardPromotionRequirements extends Command
{
    protected $signature = 'requirements:carry-forward-promotions
                            {--enrollment= : Limit processing to one target enrollment public ID}';

    protected $description = 'Carry accepted hard-copy evidence into existing promotion enrollments';

    public function handle(PreparePromotedEnrollmentRequirementsAction $prepare): int
    {
        $query = Enrollment::query()->whereNotNull('previous_enrollment_id')->orderBy('id');

        if ($publicId = $this->option('enrollment')) {
            $query->where('public_id', $publicId);
        }

        $targets = $query->get();
        if ($publicId && $targets->isEmpty()) {
            $this->error('The requested promoted enrollment was not found.');

            return self::FAILURE;
        }

        $carried = 0;
        foreach ($targets as $target) {
            $source = Enrollment::query()->findOrFail($target->previous_enrollment_id);
            $actorId = PromotionRecommendation::query()->where('created_enrollment_id', $target->id)->value('confirmed_by');
            $actor = $actorId === null ? null : User::query()->find($actorId);
            $carried += $prepare->execute($source, $target, $actor);
        }

        $this->info("Processed {$targets->count()} promoted enrollment(s); carried {$carried} accepted requirement item(s).");

        return self::SUCCESS;
    }
}
