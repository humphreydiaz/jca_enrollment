<?php

namespace App\Modules\People\Support;

final class LearnerProfileOptions
{
    /** @var array<string, string> */
    public const SEXES = [
        'male' => 'Male',
        'female' => 'Female',
    ];

    /** @var array<string, string> */
    public const GUARDIAN_RELATIONSHIPS = [
        'father' => 'Father',
        'mother' => 'Mother',
        'grandparent' => 'Grandparent',
        'sibling' => 'Sibling',
        'aunt_or_uncle' => 'Aunt or uncle',
        'legal_guardian' => 'Legal guardian',
        'other' => 'Other',
    ];

    /** @var array<string, string> */
    public const DISABILITY_TYPES = [
        'visual_impairment_blind' => 'Visual impairment — blind',
        'visual_impairment_low_vision' => 'Visual impairment — low vision',
        'hearing_impairment' => 'Hearing impairment',
        'learning_disability' => 'Learning disability',
        'intellectual_disability' => 'Intellectual disability',
        'autism_spectrum_disorder' => 'Autism spectrum disorder',
        'emotional_behavioral_disorder' => 'Emotional-behavioral disorder',
        'orthopedic_physical_disability' => 'Orthopedic/physical disability',
        'speech_language_disorder' => 'Speech/language disorder',
        'cerebral_palsy' => 'Cerebral palsy',
        'special_health_problem_chronic_disease' => 'Special health problem/chronic disease',
        'multiple_disability' => 'Multiple disability',
        'other' => 'Other',
    ];

    private function __construct() {}
}
