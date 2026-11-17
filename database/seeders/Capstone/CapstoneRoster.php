<?php

namespace Database\Seeders\Capstone;

/**
 * The fictional people behind the capstone demonstration school year.
 *
 * Every name, birthday, address, and story here is invented. Emails use the reserved
 * "gmail.test" domain (".test" can never deliver mail) and nobody has a mobile number,
 * so the live SMS and mail settings on the hosted site can never reach a real person.
 */
final class CapstoneRoster
{
    public const EMAIL_DOMAIN = 'gmail.test';

    /**
     * Teachers: one adviser per official section, also used as subject teachers.
     * staff_number is the Staff ID issued on the hosted capstone site on 2026-10-07 (its year prefix
     * follows employed_on), pinned so every rebuild signs in with the same IDs.
     *
     * @var list<array{first: string, last: string, sex: string, employed_on: string, grade: string, section: string, subjects: list<string>}>
     */
    public const TEACHERS = [
        ['first' => 'Ma. Lourdes', 'last' => 'Fernandez', 'staff_number' => '18-JCA-946', 'sex' => 'female', 'employed_on' => '2018-06-04', 'grade' => 'NUR', 'section' => 'Hope', 'subjects' => ['MAPEH', 'GMRC']],
        ['first' => 'Rosalie', 'last' => 'Manalo', 'staff_number' => '20-JCA-992', 'sex' => 'female', 'employed_on' => '2020-06-15', 'grade' => 'JK', 'section' => 'Joy', 'subjects' => ['ENG', 'LANG']],
        ['first' => 'Kristine Joy', 'last' => 'Cabrera', 'staff_number' => '23-JCA-602', 'sex' => 'female', 'employed_on' => '2023-06-05', 'grade' => 'JK', 'section' => 'Peace', 'subjects' => ['FIL', 'RL']],
        ['first' => 'Arlene', 'last' => 'Soriano', 'staff_number' => '16-JCA-213', 'sex' => 'female', 'employed_on' => '2016-06-06', 'grade' => 'SK', 'section' => 'Love', 'subjects' => ['MATH', 'EPP']],
        ['first' => 'Jennifer', 'last' => 'Ocampo', 'staff_number' => '21-JCA-225', 'sex' => 'female', 'employed_on' => '2021-06-07', 'grade' => 'SK', 'section' => 'Wisdom', 'subjects' => ['ESP', 'GMRC']],
        ['first' => 'Gloria', 'last' => 'Tolentino', 'staff_number' => '12-JCA-820', 'sex' => 'female', 'employed_on' => '2012-06-04', 'grade' => 'G1', 'section' => 'Paul', 'subjects' => ['RL', 'LANG', 'MAPEH']],
        ['first' => 'Marites', 'last' => 'Domingo', 'staff_number' => '15-JCA-676', 'sex' => 'female', 'employed_on' => '2015-06-01', 'grade' => 'G1', 'section' => 'Philip', 'subjects' => ['RL', 'MAK', 'AP']],
        ['first' => 'Leah Mae', 'last' => 'Rosales', 'staff_number' => '19-JCA-365', 'sex' => 'female', 'employed_on' => '2019-06-03', 'grade' => 'G2', 'section' => 'Peter', 'subjects' => ['ENG', 'FIL']],
        ['first' => 'Cherry Ann', 'last' => 'Mercado', 'staff_number' => '22-JCA-664', 'sex' => 'female', 'employed_on' => '2022-06-13', 'grade' => 'G2', 'section' => 'Luke', 'subjects' => ['MATH', 'MAK']],
        ['first' => 'Rowena', 'last' => 'Salvador', 'staff_number' => '14-JCA-759', 'sex' => 'female', 'employed_on' => '2014-06-02', 'grade' => 'G3', 'section' => 'John', 'subjects' => ['SCI', 'MAPEH']],
        ['first' => 'Ma. Cristina', 'last' => 'Valdez', 'staff_number' => '17-JCA-852', 'sex' => 'female', 'employed_on' => '2017-06-05', 'grade' => 'G3', 'section' => 'Joshua', 'subjects' => ['FIL', 'ESP']],
        ['first' => 'Edwin', 'last' => 'Panganiban', 'staff_number' => '13-JCA-147', 'sex' => 'male', 'employed_on' => '2013-06-03', 'grade' => 'G4', 'section' => 'Stephen', 'subjects' => ['MATH', 'EPP']],
        ['first' => 'Analyn', 'last' => 'De Guzman', 'staff_number' => '20-JCA-522', 'sex' => 'female', 'employed_on' => '2020-06-15', 'grade' => 'G4', 'section' => 'Ezekiel', 'subjects' => ['ENG', 'ESP']],
        ['first' => 'Ramil', 'last' => 'Bautista', 'staff_number' => '18-JCA-842', 'sex' => 'male', 'employed_on' => '2018-06-04', 'grade' => 'G5', 'section' => 'David', 'subjects' => ['AP', 'ESP']],
        ['first' => 'Joanna Marie', 'last' => 'Aquino', 'staff_number' => '24-JCA-868', 'sex' => 'female', 'employed_on' => '2024-06-03', 'grade' => 'G5', 'section' => 'Mark', 'subjects' => ['SCI', 'MATH']],
        ['first' => 'Felicidad', 'last' => 'Castillo', 'staff_number' => '10-JCA-606', 'sex' => 'female', 'employed_on' => '2010-06-07', 'grade' => 'G6', 'section' => 'Matthew', 'subjects' => ['FIL', 'AP']],
        ['first' => 'Ronaldo', 'last' => 'Mendoza', 'staff_number' => '16-JCA-245', 'sex' => 'male', 'employed_on' => '2016-06-06', 'grade' => 'G7', 'section' => 'Isaiah', 'subjects' => ['SCI', 'TLE']],
        ['first' => 'Michelle Ann', 'last' => 'Navarro', 'staff_number' => '19-JCA-625', 'sex' => 'female', 'employed_on' => '2019-06-03', 'grade' => 'G8', 'section' => 'Jeremiah', 'subjects' => ['ENG', 'AP']],
        ['first' => 'Dennis', 'last' => 'Villanueva', 'staff_number' => '15-JCA-422', 'sex' => 'male', 'employed_on' => '2015-06-01', 'grade' => 'G9', 'section' => 'Isaac', 'subjects' => ['MATH', 'MAPEH']],
        ['first' => 'Princess Joy', 'last' => 'Ramos', 'staff_number' => '21-JCA-818', 'sex' => 'female', 'employed_on' => '2021-06-07', 'grade' => 'G10', 'section' => 'Jacob', 'subjects' => ['TLE', 'EPP', 'MAPEH']],
    ];

    /**
     * Enrolled learners, in the order the families came to the Registrar.
     * student_number is the Student ID issued on the hosted capstone site on 2026-10-07, pinned so every
     * rebuild signs in with the same IDs.
     *
     * walk_in: the day the Registrar issued enrollment access (the family completes the form within a few days).
     * plan / pays: payment plan and how the family has kept up (full, ontime, q1only, behind).
     * extras: optional fees the family took (books are selected by default; PE uniform is opt-in).
     * method: how the family usually pays.
     *
     * @var list<array<string, mixed>>
     */
    public const LEARNERS = [
        [
            'key' => 'mark-sison', 'student_number' => 'JCA-26-582', 'grade' => 'G8', 'section' => 'Jeremiah', 'walk_in' => '2026-05-11 08:42',
            'first' => 'Mark Joseph', 'middle' => 'Doria', 'last' => 'Sison', 'sex' => 'male', 'birth_date' => '2012-12-05', 'lrn' => '101523120318',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '112 Avenida Rizal West, Poblacion, Lingayen, Pangasinan',
            'father' => ['Joselito', 'Manuel', 'Sison'], 'mother' => ['Marivic', 'Abrenica', 'Doria'], 'guardian' => 'mother',
            'plan' => 'full', 'pays' => 'full', 'extras' => ['BKS', 'PEU'], 'method' => 'cash',
        ],
        [
            'key' => 'andrea-calimlim', 'student_number' => 'JCA-26-724', 'grade' => 'G7', 'section' => 'Isaiah', 'walk_in' => '2026-05-12 09:15',
            'first' => 'Andrea Nicole', 'middle' => 'Ferrer', 'last' => 'Calimlim', 'sex' => 'female', 'birth_date' => '2013-11-02', 'lrn' => '101523130472',
            'birth_place' => 'Dagupan City, Pangasinan', 'mother_tongue' => 'Ilocano', 'address' => '27 Maramba St., Libsong East, Lingayen, Pangasinan',
            'father' => ['Arnel', 'Tabios', 'Calimlim'], 'mother' => ['Rhea', 'Bonifacio', 'Ferrer'], 'guardian' => 'mother',
            'plan' => 'full', 'pays' => 'full', 'extras' => ['BKS'], 'method' => 'bank_transfer',
        ],
        [
            'key' => 'isabel-bulatao', 'student_number' => 'JCA-26-176', 'grade' => 'G10', 'section' => 'Jacob', 'walk_in' => '2026-05-12 13:40',
            'first' => 'Ma. Isabel', 'middle' => 'Serafica', 'last' => 'Bulatao', 'sex' => 'female', 'birth_date' => '2011-01-16', 'lrn' => '101523110129',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '8 Gov. Sison St., Poblacion, Lingayen, Pangasinan',
            'father' => ['Reynaldo', 'Castro', 'Bulatao'], 'mother' => ['Leonora', 'Gaerlan', 'Serafica'], 'guardian' => 'father',
            'plan' => 'full', 'pays' => 'full', 'extras' => ['BKS'], 'method' => 'bank_transfer',
        ],
        [
            'key' => 'mateo-ignacio', 'student_number' => 'JCA-26-411', 'grade' => 'G1', 'section' => 'Paul', 'walk_in' => '2026-05-13 10:05',
            'first' => 'Mateo Luis', 'middle' => 'Sagun', 'last' => 'Ignacio', 'sex' => 'male', 'birth_date' => '2019-09-10', 'lrn' => '101523190851',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '54 Purok 3, Pangapisan North, Lingayen, Pangasinan',
            'father' => ['Carlo', 'Mejia', 'Ignacio'], 'mother' => ['Kristel', 'Agbayani', 'Sagun'], 'guardian' => 'mother',
            'plan' => 'monthly', 'pays' => 'ontime', 'extras' => ['BKS', 'PEU'], 'method' => 'e_wallet',
        ],
        [
            'key' => 'liam-torio', 'student_number' => 'JCA-26-846', 'grade' => 'SK', 'section' => 'Love', 'walk_in' => '2026-05-14 09:30',
            'first' => 'Liam Gabriel', 'middle' => 'Cayabyab', 'last' => 'Torio', 'sex' => 'male', 'birth_date' => '2021-01-29', 'lrn' => null,
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '19 Purok Malinis, Domalandan Center, Lingayen, Pangasinan',
            'father' => ['Jerome', 'Fajardo', 'Torio'], 'mother' => ['Aileen', 'Dumlao', 'Cayabyab'], 'guardian' => 'mother',
            'plan' => 'quarterly', 'pays' => 'ontime', 'extras' => ['BKS', 'PEU'], 'method' => 'cash',
        ],
        [
            'key' => 'sean-gamboa', 'student_number' => 'JCA-26-884', 'grade' => 'G8', 'section' => 'Jeremiah', 'walk_in' => '2026-05-15 14:10',
            'first' => 'Sean Matthew', 'middle' => 'Bernal', 'last' => 'Gamboa', 'sex' => 'male', 'birth_date' => '2012-09-14', 'lrn' => '101523120096',
            'birth_place' => 'San Carlos City, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '3 Alvear St., Libsong West, Lingayen, Pangasinan',
            'father' => ['Ricardo', 'Lim', 'Gamboa'], 'mother' => ['Jocelyn', 'Untalan', 'Bernal'], 'guardian' => 'mother',
            'plan' => 'semiannual', 'pays' => 'ontime', 'extras' => ['BKS'], 'method' => 'cash',
        ],
        [
            'key' => 'christian-macaraeg', 'student_number' => 'JCA-26-384', 'grade' => 'G9', 'section' => 'Isaac', 'walk_in' => '2026-05-18 08:55',
            'first' => 'Christian Paul', 'middle' => 'Junio', 'last' => 'Macaraeg', 'sex' => 'male', 'birth_date' => '2011-10-21', 'lrn' => '101523110744',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '61 Purok 5, Estanza, Lingayen, Pangasinan',
            'father' => ['Danilo', 'Rosario', 'Macaraeg'], 'mother' => ['Marilou', 'Quinto', 'Junio'], 'guardian' => 'mother',
            'plan' => 'monthly', 'pays' => 'ontime', 'extras' => ['BKS', 'PEU'], 'method' => 'e_wallet',
        ],
        [
            'key' => 'chloe-reyes', 'student_number' => 'JCA-26-352', 'grade' => 'G5', 'section' => 'Mark', 'walk_in' => '2026-05-18 15:20',
            'first' => 'Chloe Anne', 'middle' => 'Fabros', 'last' => 'Reyes', 'sex' => 'female', 'birth_date' => '2015-02-14', 'lrn' => '101523150263',
            'birth_place' => 'Dagupan City, Pangasinan', 'mother_tongue' => 'Ilocano', 'address' => '22 Purok 2, Sabangan, Lingayen, Pangasinan',
            'father' => ['Noel', 'Garcia', 'Reyes'], 'mother' => ['Grace', 'Mangaoang', 'Fabros'], 'guardian' => 'mother',
            'plan' => 'quarterly', 'pays' => 'ontime', 'extras' => ['BKS'], 'method' => 'cash',
        ],
        [
            'key' => 'kenji-nisperos', 'student_number' => 'JCA-26-579', 'grade' => 'G2', 'section' => 'Peter', 'walk_in' => '2026-05-19 10:30', 'category' => 'support_review',
            'first' => 'Kenji Adrian', 'middle' => 'Lopez', 'last' => 'Nisperos', 'sex' => 'male', 'birth_date' => '2018-07-04', 'lrn' => '101523180507',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Tagalog', 'address' => '7 Purok Sampaguita, Maniboc, Lingayen, Pangasinan',
            'father' => ['Bryan', 'Sy', 'Nisperos'], 'mother' => ['Charmaine', 'Ramirez', 'Lopez'], 'guardian' => 'mother',
            'disability' => 'speech_language_disorder',
            'plan' => 'monthly', 'pays' => 'ontime', 'extras' => ['BKS', 'PEU'], 'method' => 'e_wallet',
            'support' => [
                'rationale' => 'Parent presented a 2025 developmental pediatrician report noting an expressive language delay; Kenji has speech therapy twice a month at a clinic in Dagupan City. At the readiness interview he followed two-step instructions, read simple three-letter words, and answered in short phrases. He became quiet when rushed and answered better when given time and picture cues.',
                'next_action' => 'Place in Grade 2; monthly check-in with parent',
                'observation' => ['category' => 'Communication', 'body' => 'Readiness interview (with mother present): answered with gestures first, then words when given about ten seconds of wait time. Matched pictures to words without difficulty. Mother shared the therapist\'s home-practice sheet; a copy is on file with the Registrar.'],
                'summary' => 'Kenji understands lessons well but needs extra time to answer out loud. Give him a few seconds of wait time, use picture or written cues with spoken instructions, and accept short or pointed answers during recitation. Check his understanding privately rather than in front of the class. Share any concerns with the adviser for the monthly parent check-in.',
            ],
        ],
        [
            'key' => 'gabriel-untalan', 'student_number' => 'JCA-26-509', 'grade' => 'G7', 'section' => 'Isaiah', 'walk_in' => '2026-05-20 09:05',
            'first' => 'Gabriel Lorenzo', 'middle' => 'Nable', 'last' => 'Untalan', 'sex' => 'male', 'birth_date' => '2014-03-18', 'lrn' => '101523140117',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '45 Rizal St., Libsong West, Lingayen, Pangasinan',
            'father' => ['Rommel', 'Datu', 'Untalan'], 'mother' => ['Josephine', 'Pascua', 'Nable'], 'guardian' => 'mother',
            'plan' => 'monthly', 'pays' => 'ontime', 'extras' => ['BKS', 'PEU'], 'method' => 'e_wallet',
        ],
        [
            'key' => 'trisha-estrada', 'student_number' => 'JCA-26-975', 'grade' => 'G10', 'section' => 'Jacob', 'walk_in' => '2026-05-21 10:45',
            'first' => 'Trisha Anne', 'middle' => 'Magno', 'last' => 'Estrada', 'sex' => 'female', 'birth_date' => '2011-05-19', 'lrn' => '101523110388',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '90 Purok 4, Aliwekwek, Lingayen, Pangasinan',
            'father' => ['Edgardo', 'Valencia', 'Estrada'], 'mother' => ['Cristina', 'Ancheta', 'Magno'], 'guardian' => 'mother',
            'plan' => 'quarterly', 'pays' => 'ontime', 'extras' => ['BKS'], 'method' => 'cash',
        ],
        [
            'key' => 'sophia-perez', 'student_number' => 'JCA-26-324', 'grade' => 'JK', 'section' => 'Joy', 'walk_in' => '2026-05-22 08:35',
            'first' => 'Sophia Louise', 'middle' => 'Laforteza', 'last' => 'Perez', 'sex' => 'female', 'birth_date' => '2022-04-07', 'lrn' => null,
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Tagalog', 'address' => '15 Purok 1, Baay, Lingayen, Pangasinan',
            'father' => ['Paolo', 'Ventura', 'Perez'], 'mother' => ['Diana Rose', 'Medina', 'Laforteza'], 'guardian' => 'mother',
            'plan' => 'full', 'pays' => 'full', 'extras' => ['BKS', 'PEU'], 'method' => 'cash',
        ],
        [
            'key' => 'elijah-zarate', 'student_number' => 'JCA-26-426', 'grade' => 'G5', 'section' => 'David', 'walk_in' => '2026-05-25 13:15', 'category' => 'support_review',
            'first' => 'Elijah Paolo', 'middle' => 'Bernardo', 'last' => 'Zarate', 'sex' => 'male', 'birth_date' => '2015-08-08', 'lrn' => '101523150611',
            'birth_place' => 'Urdaneta City, Pangasinan', 'mother_tongue' => 'Ilocano', 'address' => '33 Purok 6, Namolan, Lingayen, Pangasinan',
            'father' => ['Ferdinand', 'Agustin', 'Zarate'], 'mother' => ['Melinda', 'Ordoño', 'Bernardo'], 'guardian' => 'father',
            'disability' => 'autism_spectrum_disorder',
            'plan' => 'quarterly', 'pays' => 'ontime', 'extras' => ['BKS'], 'method' => 'bank_transfer',
            'support' => [
                'rationale' => 'Diagnosed with autism spectrum disorder (level 1) by a developmental pediatrician in 2022; attends occupational therapy weekly. Previous school records show grade-level work in Mathematics and Science. Father reports sensitivity to loud noise and distress when routines change without warning. Observed during the interview: calm and cooperative once told what would happen next.',
                'next_action' => 'Place in Grade 5; quarterly review with parent',
                'observation' => ['category' => 'Classroom readiness', 'body' => 'Interview and short reading and math tasks: completed the math task quickly and accurately; covered his ears when the bell rang and needed a minute to resume. Father recommends giving advance notice before schedule changes and a quiet place to reset.'],
                'summary' => 'Elijah does best with a predictable routine. Tell him ahead of time about schedule changes (assemblies, room changes, exam days). Give one instruction at a time. If the room gets loud, let him take a short break in a quiet corner. He may avoid eye contact; this is not disrespect. During examinations, a seat away from the door helps him focus.',
            ],
        ],
        [
            'key' => 'justin-tamayo', 'student_number' => 'JCA-26-343', 'grade' => 'G7', 'section' => 'Isaiah', 'walk_in' => '2026-05-26 11:20',
            'first' => 'Justin Carl', 'middle' => 'Mamaril', 'last' => 'Tamayo', 'sex' => 'male', 'birth_date' => '2014-06-25', 'lrn' => '101523140590',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '4 Purok 2, Tonton, Lingayen, Pangasinan',
            'father' => ['Wilfredo', 'Sarmiento', 'Tamayo'], 'mother' => ['Imelda', 'Rabago', 'Mamaril'], 'guardian' => 'mother',
            'plan' => 'quarterly', 'pays' => 'ontime', 'extras' => ['BKS', 'PEU'], 'method' => 'cash',
        ],
        [
            'key' => 'hannah-oliveros', 'student_number' => 'JCA-26-896', 'grade' => 'G8', 'section' => 'Jeremiah', 'walk_in' => '2026-05-27 09:50',
            'first' => 'Hannah Grace', 'middle' => 'Tiongson', 'last' => 'Oliveros', 'sex' => 'female', 'birth_date' => '2013-04-30', 'lrn' => '101523130215',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '76 Purok 3, Lasip, Lingayen, Pangasinan',
            'father' => ['Gerardo', 'Mercado', 'Oliveros'], 'mother' => ['Ruby', 'Flores', 'Tiongson'], 'guardian' => 'mother',
            'plan' => 'quarterly', 'pays' => 'ontime', 'extras' => ['BKS'], 'method' => 'e_wallet',
        ],
        [
            'key' => 'ysabelle-quiambao', 'student_number' => 'JCA-26-727', 'grade' => 'G3', 'section' => 'John', 'walk_in' => '2026-05-28 14:00',
            'first' => 'Ysabelle', 'middle' => 'Gonzales', 'last' => 'Quiambao', 'sex' => 'female', 'birth_date' => '2017-12-12', 'lrn' => '101523170934',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '12 Purok 4, Libsong East, Lingayen, Pangasinan',
            'father' => ['Jonathan', 'Aquino', 'Quiambao'], 'mother' => ['Liza', 'Del Rosario', 'Gonzales'], 'guardian' => 'mother',
            'plan' => 'semiannual', 'pays' => 'ontime', 'extras' => ['BKS', 'PEU'], 'method' => 'cash',
        ],
        [
            'key' => 'joaquin-pascua', 'student_number' => 'JCA-26-963', 'grade' => 'G6', 'section' => 'Matthew', 'walk_in' => '2026-05-29 10:10',
            'first' => 'Joaquin Miguel', 'middle' => 'Dizon', 'last' => 'Pascua', 'sex' => 'male', 'birth_date' => '2014-10-27', 'lrn' => '101523140802',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '58 Purok 7, Malimpuec, Lingayen, Pangasinan',
            'father' => ['Rolando', 'Biagtan', 'Pascua'], 'mother' => ['Analiza', 'Ramos', 'Dizon'], 'guardian' => 'mother',
            'four_ps' => '015518030-0042-00017',
            'plan' => 'monthly', 'pays' => 'behind', 'extras' => ['BKS'], 'method' => 'cash',
        ],
        [
            'key' => 'bea-vinluan', 'student_number' => 'JCA-26-524', 'grade' => 'G9', 'section' => 'Isaac', 'walk_in' => '2026-06-01 08:40',
            'first' => 'Bea Kristine', 'middle' => 'Resuello', 'last' => 'Vinluan', 'sex' => 'female', 'birth_date' => '2012-03-03', 'lrn' => '101523120671',
            'birth_place' => 'Binmaley, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '6 Purok 1, Capandanan, Lingayen, Pangasinan',
            'father' => ['Alfredo', 'Soriano', 'Vinluan'], 'mother' => ['Teresita', 'Nable', 'Resuello'], 'guardian' => 'mother',
            'plan' => 'semiannual', 'pays' => 'ontime', 'extras' => ['BKS', 'PEU'], 'method' => 'bank_transfer',
        ],
        [
            'key' => 'zia-ancheta', 'student_number' => 'JCA-26-981', 'grade' => 'SK', 'section' => 'Wisdom', 'walk_in' => '2026-06-01 13:25',
            'first' => 'Zia Marie', 'middle' => 'Frias', 'last' => 'Ancheta', 'sex' => 'female', 'birth_date' => '2020-11-23', 'lrn' => null,
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Ilocano', 'address' => '41 Purok 2, Naguelguel, Lingayen, Pangasinan',
            'father' => ['Mark Anthony', 'Tabuena', 'Ancheta'], 'mother' => ['Jessa', 'Galang', 'Frias'], 'guardian' => 'grandparent',
            'guardian_person' => ['Corazon', 'Lomibao', 'Frias'],
            'plan' => 'monthly', 'pays' => 'ontime', 'extras' => ['BKS'], 'method' => 'cash',
        ],
        [
            'key' => 'nathaniel-rivera', 'student_number' => 'JCA-26-310', 'grade' => 'G4', 'section' => 'Ezekiel', 'walk_in' => '2026-06-02 09:35', 'category' => 'support_review',
            'first' => 'Nathaniel', 'middle' => 'Uy', 'last' => 'Rivera', 'sex' => 'male', 'birth_date' => '2016-05-30', 'lrn' => '101523160448',
            'birth_place' => 'Dagupan City, Pangasinan', 'mother_tongue' => 'Tagalog', 'address' => '29 Purok 5, Pangapisan South, Lingayen, Pangasinan',
            'father' => ['Raymond', 'Co', 'Rivera'], 'mother' => ['Jasmine', 'Tan', 'Uy'], 'guardian' => 'mother',
            'disability' => 'learning_disability',
            'plan' => 'semiannual', 'pays' => 'ontime', 'extras' => ['BKS', 'PEU'], 'method' => 'bank_transfer',
            'support' => [
                'rationale' => 'A 2025 psycho-educational assessment (copy presented by the mother) indicates a specific learning disability in reading (dyslexia). His Grade 3 report card shows strong Mathematics but low Reading and Language marks. Reads slowly with letter reversals; understands well when a passage is read aloud.',
                'next_action' => 'Place in Grade 4; adviser to note reading accommodations',
                'observation' => ['category' => 'Reading', 'body' => 'Readiness task: read a short Grade 3 passage slowly with several reversals (b/d), but answered all comprehension questions correctly when the passage was read to him. Solved the Math items quickly. Mother asked that test directions be read aloud.'],
                'summary' => 'Nathaniel understands lessons well but reads slowly. On reading-heavy quizzes and tests, give extra time where possible and read the directions aloud. Allow oral answers for comprehension checks when writing slows him down, and use clear, uncluttered worksheets with larger print. He is strong in Mathematics and enjoys hands-on activities.',
            ],
        ],
        [
            'key' => 'samantha-mangaoang', 'student_number' => 'JCA-26-404', 'grade' => 'G4', 'section' => 'Stephen', 'walk_in' => '2026-06-03 10:55',
            'first' => 'Samantha Kate', 'middle' => 'Villar', 'last' => 'Mangaoang', 'sex' => 'female', 'birth_date' => '2016-03-21', 'lrn' => '101523160129',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '70 Purok 3, Domalandan West, Lingayen, Pangasinan',
            'father' => ['Arturo', 'Bravo', 'Mangaoang'], 'mother' => ['Rachelle', 'Estacio', 'Villar'], 'guardian' => 'mother',
            'plan' => 'quarterly', 'pays' => 'q1only', 'extras' => ['BKS'], 'method' => 'cash',
        ],
        [
            'key' => 'ralph-caguioa', 'student_number' => 'JCA-26-502', 'grade' => 'G10', 'section' => 'Jacob', 'walk_in' => '2026-06-04 14:30',
            'first' => 'Ralph Jericho', 'middle' => 'Dulay', 'last' => 'Caguioa', 'sex' => 'male', 'birth_date' => '2010-12-08', 'lrn' => '101523100953',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '18 Purok 6, Basing, Lingayen, Pangasinan',
            'father' => ['Rodel', 'Mangrobang', 'Caguioa'], 'mother' => ['Divina', 'Ferrer', 'Dulay'], 'guardian' => 'aunt_or_uncle',
            'guardian_person' => ['Lorna', 'Caguioa', 'Dela Peña'],
            'plan' => 'monthly', 'pays' => 'behind', 'extras' => ['BKS'], 'method' => 'cash',
        ],
        [
            'key' => 'ezekiel-padilla', 'student_number' => 'JCA-26-475', 'grade' => 'NUR', 'section' => 'Hope', 'walk_in' => '2026-06-05 09:20',
            'first' => 'Ezekiel James', 'middle' => 'Quinto', 'last' => 'Padilla', 'sex' => 'male', 'birth_date' => '2023-02-11', 'lrn' => null,
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '9 Purok 1, Balangobong, Lingayen, Pangasinan',
            'father' => ['Jayson', 'Lacsamana', 'Padilla'], 'mother' => ['Kimberly', 'Ocampo', 'Quinto'], 'guardian' => 'mother',
            'plan' => 'monthly', 'pays' => 'ontime', 'extras' => ['BKS', 'PEU'], 'method' => 'e_wallet',
        ],
        [
            'key' => 'princess-erfe', 'student_number' => 'JCA-26-987', 'grade' => 'G7', 'section' => 'Isaiah', 'walk_in' => '2026-06-08 08:30',
            'first' => 'Princess Mae', 'middle' => 'Lomboy', 'last' => 'Erfe', 'sex' => 'female', 'birth_date' => '2014-01-09', 'lrn' => '101523140266',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '101 Purok 8, Bantayan, Lingayen, Pangasinan',
            'father' => ['Eduardo', 'Paragas', 'Erfe'], 'mother' => ['Rosemarie', 'Tamondong', 'Lomboy'], 'guardian' => 'mother',
            'four_ps' => '015518030-0042-00109',
            'plan' => 'monthly', 'pays' => 'behind', 'extras' => ['BKS'], 'method' => 'cash',
        ],
        [
            'key' => 'johnrey-abalos', 'student_number' => 'JCA-26-402', 'grade' => 'G9', 'section' => 'Isaac', 'walk_in' => '2026-06-10 10:40', 'category' => 'transferee',
            'first' => 'John Rey', 'middle' => 'Galvan', 'last' => 'Abalos', 'sex' => 'male', 'birth_date' => '2011-08-12', 'lrn' => '101523110547',
            'birth_place' => 'Dagupan City, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '37 Purok 2, Libsong West, Lingayen, Pangasinan',
            'father' => ['Renato', 'Cruz', 'Abalos'], 'mother' => ['Evangeline', 'Soliven', 'Galvan'], 'guardian' => 'father',
            'previous_school' => ['Dagupan City National High School', 'Dagupan City, Pangasinan'],
            'plan' => 'quarterly', 'pays' => 'ontime', 'extras' => ['BKS', 'PEU'], 'method' => 'cash',
        ],
        [
            'key' => 'kathleen-datuin', 'student_number' => 'JCA-26-588', 'grade' => 'G8', 'section' => 'Jeremiah', 'walk_in' => '2026-07-13 09:00', 'category' => 'transferee',
            'first' => 'Kathleen Joy', 'middle' => 'Velasco', 'last' => 'Datuin', 'sex' => 'female', 'birth_date' => '2013-02-27', 'lrn' => '101523130883',
            'birth_place' => 'Quezon City, Metro Manila', 'mother_tongue' => 'Tagalog', 'address' => '11 Purok 4, Domalandan East, Lingayen, Pangasinan',
            'father' => ['Ronnie', 'Bautista', 'Datuin'], 'mother' => ['Maricar', 'Santiago', 'Velasco'], 'guardian' => 'mother',
            'previous_school' => ['Quezon City Science-Oriented Christian School', 'Quezon City, Metro Manila'],
            'plan' => 'monthly', 'pays' => 'q1only', 'extras' => ['BKS'], 'method' => 'e_wallet',
        ],
    ];

    /**
     * Families still in the Registrar's queue on the day the data was taken, so the review screens are not empty.
     *
     * @var list<array<string, mixed>>
     */
    public const PENDING = [
        [
            'key' => 'aira-fabia', 'student_number' => 'JCA-26-331', 'grade' => 'G3', 'walk_in' => '2026-10-01 10:15', 'category' => 'transferee', 'stage' => 'under_review',
            'first' => 'Aira Jean', 'middle' => 'Cruz', 'last' => 'Fabia', 'sex' => 'female', 'birth_date' => '2017-06-19', 'lrn' => '101523170412',
            'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '25 Purok 3, Sabangan, Lingayen, Pangasinan',
            'father' => ['Jun', 'Mendiola', 'Fabia'], 'mother' => ['Lovely', 'Abella', 'Cruz'], 'guardian' => 'mother',
            'previous_school' => ['Lingayen I Central School', 'Lingayen, Pangasinan'],
        ],
        [
            'key' => 'miguel-delapaz', 'student_number' => 'JCA-26-420', 'grade' => 'G7', 'walk_in' => '2026-10-05 15:05', 'category' => 'regular', 'stage' => 'draft',
            'first' => 'Miguel Antonio', 'middle' => 'Sison', 'last' => 'Dela Paz', 'sex' => 'male', 'birth_date' => '2014-05-02', 'lrn' => null,
        ],
    ];

    /** Portal announcements (no SMS), posted by the Admin through the year. */
    public const ANNOUNCEMENTS = [
        ['at' => '2026-05-04 08:30', 'roles' => null, 'title' => 'Enrollment for School Year 2026-2027 is open',
            'body' => "Enrollment for School Year 2026-2027 is now open, Monday to Friday, 8:00 AM to 4:00 PM at the Registrar's office.\n\nPlease bring the learner's PSA birth certificate and latest report card (SF9). Transferees should also bring Form 137 or a request letter for it. The Registrar will give you a Student ID and a temporary password to finish the application form online."],
        ['at' => '2026-06-01 09:00', 'roles' => null, 'title' => 'First day of classes: Monday, June 8',
            'body' => "Classes for School Year 2026-2027 start on Monday, June 8, 2026. Learners should be in school by 7:45 AM for the flag ceremony.\n\nPreschool batches follow their own class hours; please check your child's section schedule in the portal. See you on the first day!"],
        ['at' => '2026-07-27 10:00', 'roles' => ['guardian', 'student'], 'title' => 'First Quarterly Examination on August 19-20',
            'body' => "The First Quarterly Examination is on Wednesday and Thursday, August 19-20, 2026.\n\nExamination permits are released by the Accounting office from July 20. Payments due on or before August 9 must be settled to receive a permit. You can check what is due in the Finance page of the portal."],
        ['at' => '2026-08-24 08:15', 'roles' => ['teacher'], 'title' => 'Encoding of First Quarter grades',
            'body' => "Please encode the First Quarter grades of your classes in the portal on or before Friday, September 4, 2026. Grades stay as drafts until all four quarters are complete.\n\nFor learners with an approved support summary, review the summary before finalizing your class record."],
        ['at' => '2026-09-28 09:30', 'roles' => ['guardian', 'student'], 'title' => 'Second Quarterly Examination on October 19-20',
            'body' => "The Second Quarterly Examination is on Monday and Tuesday, October 19-20, 2026.\n\nPermits are available at the Accounting office from September 19 until the last examination day. Payments due on or before October 9 must be settled to receive a permit."],
    ];
}
