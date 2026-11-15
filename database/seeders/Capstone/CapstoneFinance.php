<?php

namespace Database\Seeders\Capstone;

/**
 * Financial configuration for School Year 2026-2027 in the capstone demonstration data.
 *
 * Amounts follow the school's posted fee sheet ("School Fees Complete Breakdown.jpg" and
 * "PE Uniform.jpg" in public/images/school_fees_scanned): Pre-elementary PHP 18,088.25,
 * Grades 1-6 PHP 19,903.25 and Grades 7-10 PHP 21,850.00 in required fees, with books,
 * PE uniform, and the graduation/moving-up fee as separately chosen items.
 * Examination periods follow the JCSGO 2026-2027 school calendar (see SchoolCalendarExamPeriodsSeeder).
 */
final class CapstoneFinance
{
    /** @var list<array{code: string, name: string, category: string, is_mandatory: bool, is_conditional: bool, is_permit_critical: bool, max_quantity: ?int}> */
    public const FEE_TYPES = [
        ['code' => 'TUI', 'name' => 'Tuition Fee', 'category' => 'Tuition', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => true, 'max_quantity' => 1],
        ['code' => 'MSC', 'name' => 'Miscellaneous Fee', 'category' => 'School Fee', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => true, 'max_quantity' => 1],
        ['code' => 'COMP', 'name' => 'Computer Fee', 'category' => 'School Fee', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => null],
        ['code' => 'ACLAB', 'name' => 'AC/Lab Fee', 'category' => 'School Fee', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => null],
        ['code' => 'SID', 'name' => 'School ID', 'category' => 'School Fee', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => null],
        ['code' => 'INS', 'name' => 'Insurance (Group)', 'category' => 'School Fee', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => null],
        ['code' => 'REG', 'name' => 'Registration', 'category' => 'School Fee', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => null],
        ['code' => 'BKS', 'name' => 'Books', 'category' => 'Learning Materials', 'is_mandatory' => false, 'is_conditional' => true, 'is_permit_critical' => false, 'max_quantity' => 1],
        ['code' => 'PEU', 'name' => 'PE Uniform', 'category' => 'Uniform', 'is_mandatory' => false, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => 1],
        ['code' => 'GRF', 'name' => 'Graduation/Recognition Fee', 'category' => 'Activity', 'is_mandatory' => false, 'is_conditional' => true, 'is_permit_critical' => false, 'max_quantity' => 1],
    ];

    /** Required fees per school stage (sums: 18,088.25 / 19,903.25 / 21,850.00). */
    public const REQUIRED_FEES = [
        'Pre-elementary' => ['TUI' => '11650.25', 'MSC' => '4668.00', 'ACLAB' => '1000.00', 'SID' => '110.00', 'INS' => '110.00', 'REG' => '550.00'],
        'Elementary' => ['TUI' => '11815.25', 'MSC' => '4668.00', 'COMP' => '1650.00', 'ACLAB' => '1000.00', 'SID' => '110.00', 'INS' => '110.00', 'REG' => '550.00'],
        'Junior High School' => ['TUI' => '11250.00', 'MSC' => '5550.00', 'ACLAB' => '3650.00', 'SID' => '200.00', 'INS' => '200.00', 'REG' => '1000.00'],
    ];

    public const STAGE_TOTALS = ['Pre-elementary' => '18088.25', 'Elementary' => '19903.25', 'Junior High School' => '21850.00'];

    /** Books (tentative price on the sheet). Prep = Senior Kinder; Kinder = Junior Kinder. */
    public const BOOKS = [
        'NUR' => '4200.00', 'JK' => '5100.00', 'SK' => '5200.00',
        'G1' => '6300.00', 'G2' => '6500.00', 'G3' => '6500.00', 'G4' => '7500.00', 'G5' => '7500.00', 'G6' => '7500.00',
        'G7' => '7500.00', 'G8' => '7500.00', 'G9' => '7500.00', 'G10' => '7500.00',
    ];

    public const PE_UNIFORM = [
        'NUR' => '700.00', 'JK' => '750.00', 'SK' => '750.00',
        'G1' => '800.00', 'G2' => '800.00', 'G3' => '800.00', 'G4' => '800.00', 'G5' => '850.00', 'G6' => '850.00',
        'G7' => '900.00', 'G8' => '900.00', 'G9' => '900.00', 'G10' => '900.00',
    ];

    /** Graduation (Grade 6, Grade 10) and moving-up (Senior Kinder) fee, tentative price 2,850 / 1,700. */
    public const GRADUATION = [
        'SK' => ['Moving-up Fee (tentative price)', '1700.00'],
        'G6' => ['Graduation/Recognition Fee (tentative price)', '2850.00'],
        'G10' => ['Graduation/Recognition Fee (tentative price)', '2850.00'],
    ];

    public const PLAN_OPTIONS = ['full' => 'Full Payment', 'semiannual' => 'Semiannual', 'quarterly' => 'Quarterly', 'monthly' => 'Monthly'];

    /** Weekday national holidays and school breaks inside the school year; due dates move to the next business day. */
    public const NON_BUSINESS_DAYS = [
        '2026-06-12' => 'Independence Day',
        '2026-08-21' => 'Ninoy Aquino Day',
        '2026-08-31' => 'National Heroes Day',
        '2026-11-02' => "All Souls' Day",
        '2026-11-30' => 'Bonifacio Day',
        '2026-12-08' => 'Feast of the Immaculate Conception',
        '2026-12-24' => 'Christmas Eve',
        '2026-12-25' => 'Christmas Day',
        '2026-12-30' => 'Rizal Day',
        '2026-12-31' => "New Year's Eve",
        '2027-01-01' => "New Year's Day",
        '2027-03-25' => 'Maundy Thursday',
        '2027-03-26' => 'Good Friday',
    ];

    public const PRINT_SETTINGS = [
        'receipt_signatory_role' => 'Accounting Staff',
        'permit_signatory_role' => 'School Head',
        'receipt_footer' => 'Please keep this receipt. Payments are posted to the learner\'s account in the JCA portal.',
        'permit_footer' => 'Present this permit to the proctor on each examination day.',
    ];
}
