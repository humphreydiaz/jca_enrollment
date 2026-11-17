<?php

namespace Tests\Feature;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\People\Actions\IssueStudentNumberAction;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdentifierConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_mysql_namespace_lock_serializes_student_number_issuance(): void
    {
        $year = AcademicYear::query()->create([
            'code' => 'LOCK-2026',
            'name' => 'Lock School Year 2026-2027',
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-03-31',
            'status' => 'open',
        ]);
        $student = StudentProfile::query()->create([
            'first_name' => 'Lock',
            'last_name' => 'Learner',
        ]);
        DB::table('identifier_namespaces')->insert([
            'identifier_type' => 'student',
            'year_code' => '26',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        config(['database.connections.identifier_lock_holder' => config('database.connections.mysql')]);
        $holder = DB::connection('identifier_lock_holder');
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        $holder->beginTransaction();
        $holder->select('SELECT id FROM identifier_namespaces WHERE identifier_type = ? AND year_code = ? FOR UPDATE', ['student', '26']);

        try {
            app(IssueStudentNumberAction::class)->execute($student, $year);
            $this->fail('Identifier issuance must wait for the type/year namespace lock.');
        } catch (QueryException) {
            $this->assertNull($student->fresh()->student_number);
        } finally {
            $holder->rollBack();
            DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
            DB::disconnect('identifier_lock_holder');
            DB::disconnect('mysql');
            DB::reconnect('mysql');
        }

        $number = app(IssueStudentNumberAction::class)->execute($student->fresh(), $year);
        $this->assertMatchesRegularExpression('/^JCA-26-\d{3}$/', $number);
        $this->assertSame($number, $student->fresh()->student_number);
    }
}
