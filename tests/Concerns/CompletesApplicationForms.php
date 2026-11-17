<?php

namespace Tests\Concerns;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\GuardianProfile;

/** Fills a fixture draft's application form so it may be submitted (D-049 completeness check). */
trait CompletesApplicationForms
{
    protected function completeApplicationForm(Enrollment $enrollment): Enrollment
    {
        $enrollment->loadMissing(['student', 'guardian']);
        $enrollment->student->forceFill([
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan',
            'current_address' => 'Libsong West, Lingayen', 'permanent_address' => 'Libsong West, Lingayen',
            'is_4ps_recipient' => false, 'is_learner_with_disability' => false,
        ])->save();
        $guardian = $enrollment->guardian ?? GuardianProfile::query()->create(['first_name' => 'Fixture', 'last_name' => 'Guardian']);
        if (blank($guardian->mobile) && blank($guardian->email)) {
            $guardian->forceFill(['email' => 'guardian.'.$guardian->id.'@example.test'])->save();
        }
        $enrollment->forceFill(['applicant_guardian_id' => $guardian->id, 'guardian_relationship' => $enrollment->guardian_relationship ?? 'mother'])->save();

        return $enrollment->load(['student', 'guardian']);
    }
}
