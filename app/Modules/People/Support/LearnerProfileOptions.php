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

    /** Value of the "Other (please specify)" Indigenous Peoples choice; the typed name is stored instead. */
    public const INDIGENOUS_PEOPLES_OTHER = 'other';

    /**
     * Indigenous Cultural Communities/Indigenous Peoples of the Philippines, grouped by where they mainly live.
     * The group name itself is stored in student_profiles.ip_group (D-057). Reference list, not exhaustive:
     * anyone not listed is recorded through "Other (please specify)".
     *
     * @var array<string, list<string>>
     */
    public const INDIGENOUS_PEOPLES = [
        'Cordillera and Northern Luzon' => [
            'Applai', 'Balangao', 'Bago', 'Bontok', 'Bugkalot (Ilongot)', 'Gaddang', 'Ibaloi', 'Ibatan', 'Ifugao', 'Isinai',
            'Isnag (Isneg)', 'Itawis', 'Itneg (Tingguian)', 'Ivatan', 'Kalanguya (Ikalahan)', 'Kalinga', 'Kankanaey', 'Karao',
            'Malaweg', 'Yogad',
        ],
        'Central and Southern Luzon, and Bicol' => [
            'Abelling', 'Aeta (Ayta)', 'Agta', 'Agta-Cimaron', 'Agta-Tabangnon', 'Dumagat', 'Dumagat-Remontado', 'Kabihug',
        ],
        'Mindoro and Palawan' => [
            'Alangan', 'Bangon', 'Batak', 'Buhid', 'Cagayanen', 'Hanunuo', 'Iraya', 'Molbog', "Palaw'an", 'Ratagnon',
            'Tadyawan', 'Tagbanua', 'Tau-buid',
        ],
        'Visayas' => [
            'Ati', 'Eskaya', 'Magahat (Bukidnon of Negros)', 'Panay-Bukidnon (Sulod)',
        ],
        'Mindanao' => [
            'Ata Manobo', "B'laan", 'Bagobo-Klata', 'Bagobo-Tagabawa', 'Banwaon', 'Bukidnon', 'Dibabawon', 'Dulangan Manobo',
            'Higaonon', 'Kalagan', 'Kamayo', 'Lambangian', 'Mamanwa', 'Mandaya', 'Mangguangan', 'Manobo', 'Mansaka',
            'Matigsalug', 'Obo Manobo', 'Sangil', 'Subanen', "T'boli", 'Tagakaulo', 'Talaandig', 'Teduray', 'Tigwahanon',
            'Umayamnon',
        ],
        'Bangsamoro and Sulu' => [
            'Badjao (Sama-Bajau)', 'Iranun', 'Jama Mapun', 'Kalibugan', 'Maguindanaon', 'Maranao', 'Sama', 'Tausug', 'Yakan',
        ],
    ];

    /** @return list<string> every listed group name */
    public static function indigenousPeoplesGroups(): array
    {
        return array_merge(...array_values(self::INDIGENOUS_PEOPLES));
    }

    private function __construct() {}
}
