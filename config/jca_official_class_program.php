<?php

/** Owner-supplied 2026-2027 class-program snapshot, reused as a later-year draft template. */
return [
    'subjects' => [
        'GMRC' => 'Good Manners and Right Conduct',
        'RL' => 'Reading and Literacy',
        'MATH' => 'Mathematics',
        'LANG' => 'Language',
        'MAK' => 'Makabansa',
        'ENG' => 'English',
        'FIL' => 'Filipino',
        'SCI' => 'Science',
        'MAPEH' => 'MAPEH',
        'ESP' => 'Edukasyon sa Pagpapakatao',
        'AP' => 'Araling Panlipunan',
        'EPP' => 'Edukasyong Pantahanan at Pangkabuhayan',
        'TLE' => 'Technology and Livelihood Education',
    ],
    'grade_subjects' => [
        'G1' => ['GMRC', 'RL', 'MATH', 'LANG', 'MAK'],
        'G2' => ['GMRC', 'ENG', 'MATH', 'MAK', 'FIL'],
        'G3' => ['GMRC', 'MATH', 'SCI', 'FIL', 'ENG', 'MAK', 'MAPEH'],
        'G4' => ['ESP', 'MATH', 'ENG', 'FIL', 'SCI', 'AP', 'EPP', 'MAPEH'],
        'G5' => ['ESP', 'AP', 'ENG', 'FIL', 'EPP', 'SCI', 'MATH', 'MAPEH'],
        'G6' => ['ESP', 'MATH', 'AP', 'ENG', 'FIL', 'TLE', 'SCI', 'MAPEH'],
        'G7' => ['ESP', 'SCI', 'MATH', 'AP', 'ENG', 'FIL', 'TLE', 'MAPEH'],
        'G8' => ['ESP', 'TLE', 'SCI', 'MATH', 'AP', 'ENG', 'FIL', 'MAPEH'],
        'G9' => ['ESP', 'FIL', 'TLE', 'SCI', 'MATH', 'AP', 'ENG', 'MAPEH'],
        'G10' => ['ESP', 'ENG', 'FIL', 'TLE', 'SCI', 'MATH', 'AP', 'MAPEH'],
    ],
    'section_times' => [
        'G1' => [
            'Paul' => ['GMRC' => '08:20-09:00', 'RL' => '09:00-09:40', 'MATH' => '10:00-10:40', 'LANG' => '10:40-11:20', 'MAK' => '12:00-12:40'],
            'Philip' => ['GMRC' => '08:20-09:00', 'MATH' => '09:00-09:40', 'RL' => '10:00-10:40', 'MAK' => '10:40-11:20', 'LANG' => '12:00-12:40'],
        ],
        'G2' => [
            'Peter' => ['GMRC' => '08:20-09:00', 'ENG' => '09:00-09:40', 'MATH' => '10:00-10:40', 'MAK' => '10:40-11:20', 'FIL' => '12:00-12:40'],
            'Luke' => ['GMRC' => '08:20-09:00', 'MATH' => '09:00-09:40', 'ENG' => '10:00-10:40', 'FIL' => '10:40-11:20', 'MAK' => '12:00-12:40'],
        ],
        'G3' => [
            'John' => ['GMRC' => '08:20-09:05', 'MATH' => '09:05-09:50', 'SCI' => '09:50-10:35', 'FIL' => '10:50-11:35', 'ENG' => '11:35-12:20', 'MAK' => '12:50-13:35', 'MAPEH' => '13:35-14:20'],
            'Joshua' => ['MATH' => '08:20-09:05', 'GMRC' => '09:05-09:50', 'FIL' => '09:50-10:35', 'SCI' => '10:50-11:35', 'MAK' => '11:35-12:20', 'ENG' => '12:50-13:35', 'MAPEH' => '13:35-14:20'],
        ],
        'G4' => [
            'Stephen' => ['ESP' => '08:20-09:05', 'MATH' => '09:05-09:50', 'ENG' => '09:50-10:35', 'FIL' => '10:50-11:35', 'SCI' => '11:35-12:20', 'AP' => '12:50-13:35', 'EPP' => '13:35-14:20', 'MAPEH' => '14:20-15:05'],
            'Ezekiel' => ['ESP' => '08:20-09:05', 'ENG' => '09:05-09:50', 'MATH' => '09:50-10:35', 'SCI' => '10:50-11:35', 'FIL' => '11:35-12:20', 'EPP' => '12:50-13:35', 'AP' => '13:35-14:20', 'MAPEH' => '14:20-15:05'],
        ],
        'G5' => [
            'David' => ['AP' => '08:20-09:05', 'ENG' => '09:05-09:50', 'FIL' => '09:50-10:35', 'EPP' => '10:50-11:35', 'SCI' => '11:35-12:20', 'MATH' => '12:50-13:35', 'ESP' => '13:35-14:20', 'MAPEH' => '14:20-15:05'],
            'Mark' => ['ESP' => '08:20-09:05', 'AP' => '09:05-09:50', 'ENG' => '09:50-10:35', 'FIL' => '10:50-11:35', 'EPP' => '11:35-12:20', 'SCI' => '12:50-13:35', 'MATH' => '13:35-14:20', 'MAPEH' => '14:20-15:05'],
        ],
        'G6' => ['Matthew' => ['MATH' => '08:20-09:05', 'ESP' => '09:05-09:50', 'AP' => '09:50-10:35', 'ENG' => '10:50-11:35', 'FIL' => '11:35-12:20', 'TLE' => '12:50-13:35', 'SCI' => '13:35-14:20', 'MAPEH' => '14:20-15:05']],
        'G7' => ['Isaiah' => ['SCI' => '08:20-09:05', 'MATH' => '09:05-09:50', 'ESP' => '09:50-10:35', 'AP' => '10:50-11:35', 'ENG' => '11:35-12:20', 'FIL' => '12:50-13:35', 'TLE' => '13:35-14:20', 'MAPEH' => '14:20-15:05']],
        'G8' => ['Jeremiah' => ['TLE' => '08:20-09:05', 'SCI' => '09:05-09:50', 'MATH' => '09:50-10:35', 'ESP' => '10:50-11:35', 'AP' => '11:35-12:20', 'ENG' => '12:50-13:35', 'FIL' => '13:35-14:20', 'MAPEH' => '14:20-15:05']],
        'G9' => ['Isaac' => ['FIL' => '08:20-09:05', 'TLE' => '09:05-09:50', 'SCI' => '09:50-10:35', 'MATH' => '10:50-11:35', 'ESP' => '11:35-12:20', 'AP' => '12:50-13:35', 'ENG' => '13:35-14:20', 'MAPEH' => '14:20-15:05']],
        'G10' => ['Jacob' => ['ENG' => '08:20-09:05', 'FIL' => '09:05-09:50', 'TLE' => '09:50-10:35', 'SCI' => '10:50-11:35', 'MATH' => '11:35-12:20', 'ESP' => '12:50-13:35', 'AP' => '13:35-14:20', 'MAPEH' => '14:20-15:05']],
    ],
    'preschool' => [
        'SK' => [
            'Love' => 'First batch, 08:00-11:00. Free play 08:00; meeting 08:15; activities 08:35, 09:05, 09:50; recess 09:35; music 10:20; story 10:35; wrap-up 10:55.',
            'Wisdom' => 'Second batch, 11:30-14:30. Free play 11:30; meeting 11:45; activities 12:05, 12:35, 13:20; recess 13:05; music 13:50; story 14:05; wrap-up 14:25.',
        ],
        'JK' => [
            'Joy' => '08:00-10:30. Free play 08:00; meeting 08:15; activity 1 08:35; recess 09:05; activity 2 09:25; music 09:55; story 10:05; wrap-up 10:25.',
            'Peace' => '13:00-15:30. Arrival 13:00; free play 13:15; meeting 13:30; activity 1 13:50; recess 14:15; activity 2 14:35; music 15:00; story 15:10; wrap-up 15:25.',
        ],
        'NUR' => [
            'Hope' => '11:00-13:00. Free play 11:00; meeting 11:15; activity 1 11:35; recess 11:55; hands-on activity 12:15; music 12:35; story 12:45; wrap-up 12:55.',
        ],
    ],
];
