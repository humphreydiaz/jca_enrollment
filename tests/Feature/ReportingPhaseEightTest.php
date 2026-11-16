<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\Payment;
use App\Modules\Communications\Models\Notification;
use App\Modules\Communications\Models\SmsMessage;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Reporting\Actions\RequestReportExportAction;
use App\Modules\Reporting\Jobs\GenerateReportExport;
use App\Modules\Reporting\Reports\ReportCatalog;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\Shared\Audit\AuditRecorder;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportingPhaseEightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_report_catalog_role_matrix_and_crafted_filters_are_denied(): void
    {
        $admin = $this->user(['admin']);
        $registrar = $this->user(['registrar']);
        $accounting = $this->user(['accounting']);
        $teacher = $this->user(['teacher']);
        $guardian = $this->user(['guardian']);
        $student = $this->user(['student']);
        $this->assertCount(11, app(ReportCatalog::class)->definitions());
        $this->actingAs($admin)->get(route('reporting.index'))->assertOk()->assertSee('Enrollment')->assertSee('Communications');
        $this->actingAs($registrar)->get(route('reporting.show', 'enrollments'))->assertOk();
        $this->actingAs($registrar)->get(route('reporting.show', 'payments'))->assertForbidden();
        $this->actingAs($accounting)->get(route('reporting.show', 'payments'))->assertOk();
        $this->actingAs($accounting)->get(route('reporting.show', 'enrollments'))->assertForbidden();
        $this->actingAs($teacher)->get(route('reporting.show', 'students'))->assertOk();
        $this->actingAs($teacher)->get(route('reporting.show', 'billing'))->assertForbidden();
        $this->actingAs($guardian)->get(route('reporting.index'))->assertForbidden();
        $this->actingAs($student)->get(route('reporting.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('reporting.show', ['enrollments', 'include_support' => '1']))->assertRedirect()->assertSessionHasErrors('filters');
        $this->actingAs($admin)->get('/reports/not-a-report')->assertForbidden();
    }

    public function test_academic_filters_teacher_scope_totals_and_print_allowlist(): void
    {
        $admin = $this->user(['admin']);
        $teacher = $this->user(['teacher']);
        [$year, $grade, $firstSection, $secondSection] = $this->catalogFixture();
        $first = $this->enrollmentFixture($year, $grade, $firstSection, $admin, 'Assigned', 'Student');
        $second = $this->enrollmentFixture($year, $grade, $secondSection, $admin, 'Unrelated', 'Student');
        $staff = StaffProfile::query()->create(['user_id' => $teacher->id]);
        TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $firstSection->id, 'assignment_type' => 'adviser', 'is_active' => true]);

        $filters = ['school_year' => $year->public_id, 'grade' => $grade->public_id, 'section' => $firstSection->public_id, 'status' => 'enrolled'];
        $this->actingAs($admin)->get(route('reporting.show', ['enrollments'] + $filters))->assertOk()->assertSee($first->application_number)->assertDontSee($second->application_number);
        $this->actingAs($teacher)->get(route('reporting.show', 'students'))->assertOk()->assertSee('Student, Assigned')->assertDontSee('Student, Unrelated')->assertSee($firstSection->name)->assertDontSee($secondSection->name);
        $this->actingAs($teacher)->get(route('reporting.show', 'sections'))->assertOk()->assertSee($firstSection->name)->assertSee('1')->assertDontSee($secondSection->name);
        $print = $this->actingAs($admin)->get(route('reporting.print', ['enrollments'] + $filters));
        $print->assertOk()->assertSee('JCSGO CHRISTIAN ACADEMY R1 INC.')->assertSee('Enrolment Data')->assertSee('GRAND TOTAL')->assertDontSee('Primary navigation')->assertDontSee('support_observations')->assertDontSee('payment_evidence');
        $this->assertDatabaseHas('audit_logs', ['action' => 'reporting.report.printed']);
    }

    public function test_financial_and_communication_reports_exclude_private_fields(): void
    {
        $admin = $this->user(['admin']);
        [$year, $grade, $section] = $this->catalogFixture();
        $enrollment = $this->enrollmentFixture($year, $grade, $section, $admin, 'Finance', 'Student');
        $schedule = FeeSchedule::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Fictional schedule', 'version' => 1, 'status' => 'published', 'published_by' => $admin->id, 'published_at' => now()]);
        $assessment = FeeAssessment::query()->create(['assessment_reference' => 'ASM-FICTIONAL', 'enrollment_id' => $enrollment->id, 'fee_schedule_id' => $schedule->id, 'revision_number' => 1, 'status' => 'finalized', 'gross_total' => '1000.00', 'discount_total' => '100.00', 'net_total' => '900.00', 'revision_reason' => 'CONFIDENTIAL-REVISION-REASON', 'created_by' => $admin->id, 'finalized_by' => $admin->id, 'finalized_at' => now(), 'current_finalized_key' => (string) $enrollment->id]);
        Payment::query()->create(['payment_reference' => 'PAY-FICTIONAL', 'receipt_reference' => 'REC-FICTIONAL', 'fee_assessment_id' => $assessment->id, 'received_on' => today(), 'amount' => '500.00', 'method' => 'bank_transfer', 'payer_name' => 'PRIVATE-PAYER', 'method_reference' => 'PRIVATE-BANK-REFERENCE', 'bank_name' => 'PRIVATE-BANK', 'status' => 'draft', 'created_by' => $admin->id]);
        $guardian = GuardianProfile::query()->create(['first_name' => 'Message', 'last_name' => 'Guardian', 'mobile' => '09171234567']);
        $notification = Notification::query()->create(['recipient_guardian_id' => $guardian->id, 'purpose' => 'transactional', 'status' => 'failed', 'idempotency_key' => 'fictional-notification', 'rendered_body' => 'PRIVATE-MESSAGE-BODY', 'scheduled_at' => now(), 'failed_at' => now()]);
        SmsMessage::query()->create(['notification_id' => $notification->id, 'provider' => 'fake', 'recipient_mobile' => '09171234567', 'recipient_hash' => hash('sha256', '09171234567'), 'recipient_last_four' => '4567', 'provider_reference' => 'PRIVATE-PROVIDER-REFERENCE', 'status' => 'failed', 'attempt_count' => 2, 'failure_code' => 'PRIVATE-FAILURE']);

        $this->actingAs($admin)->get(route('reporting.show', 'billing'))->assertOk()->assertSee('ASM-FICTIONAL')->assertSee('900.00')->assertDontSee('CONFIDENTIAL-REVISION-REASON');
        $this->actingAs($admin)->get(route('reporting.show', 'payments'))->assertOk()->assertSee('PAY-FICTIONAL')->assertDontSee('PRIVATE-PAYER')->assertDontSee('PRIVATE-BANK')->assertDontSee('PRIVATE-BANK-REFERENCE');
        $this->actingAs($admin)->get(route('reporting.show', 'communications'))->assertOk()->assertSee('transactional')->assertSee('fake')->assertDontSee('09171234567')->assertDontSee('4567')->assertDontSee('PRIVATE-MESSAGE-BODY')->assertDontSee('PRIVATE-PROVIDER-REFERENCE')->assertDontSee('PRIVATE-FAILURE');
    }

    public function test_private_csv_export_lifecycle_formula_protection_and_reauthorization(): void
    {
        Storage::fake('reports');
        Queue::fake();
        $admin = $this->user(['admin']);
        $otherAdmin = $this->user(['admin']);
        [$year, $grade, $section] = $this->catalogFixture();
        $this->enrollmentFixture($year, $grade, $section, $admin, 'Fictional', '=HYPERLINK("bad")');
        $export = app(RequestReportExportAction::class)->execute('enrollments', ['school_year' => $year->public_id], $admin);
        (new GenerateReportExport($export->id))->handle(app(ReportCatalog::class), app(AuditRecorder::class));
        $export->refresh();
        $this->assertSame('completed', $export->status);
        Storage::disk('reports')->assertExists($export->storage_path);
        $csv = Storage::disk('reports')->get($export->storage_path);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('support_observations', $csv);
        $this->actingAs($otherAdmin)->get(route('reporting.exports.download', $export))->assertNotFound();
        $this->actingAs($admin)->get(route('reporting.exports.download', $export))->assertOk()->assertHeader('x-content-type-options', 'nosniff');
        $this->assertSame(1, $export->fresh()->download_count);
        $export->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($admin)->get(route('reporting.exports.download', $export))->assertStatus(410);
        $export->update(['expires_at' => now()->addHour()]);
        $path = $export->storage_path;
        $this->actingAs($admin)->delete(route('reporting.exports.destroy', $export))->assertRedirect();
        Storage::disk('reports')->assertMissing($path);
        $this->assertDatabaseHas('export_jobs', ['id' => $export->id, 'status' => 'deleted']);

        $registrar = $this->user(['registrar']);
        $revoked = app(RequestReportExportAction::class)->execute('enrollments', [], $registrar);
        (new GenerateReportExport($revoked->id))->handle(app(ReportCatalog::class), app(AuditRecorder::class));
        app(AssignRolesAction::class)->execute($registrar, ['teacher'], $admin);
        $this->actingAs($registrar->fresh())->get(route('reporting.exports.download', $revoked))->assertNotFound();
    }

    public function test_completed_export_whose_file_is_gone_is_not_offered_and_explains_instead_of_404(): void
    {
        Storage::fake('reports');
        Queue::fake();
        $admin = $this->user(['admin']);
        [$year, $grade, $section] = $this->catalogFixture();
        $this->enrollmentFixture($year, $grade, $section, $admin, 'Fictional', 'Learner');
        $export = app(RequestReportExportAction::class)->execute('enrollments', [], $admin);
        (new GenerateReportExport($export->id))->handle(app(ReportCatalog::class), app(AuditRecorder::class));
        $export->refresh();

        $this->actingAs($admin)->get(route('reporting.index'))->assertOk()->assertSee('Download CSV');

        // e.g. the database was copied to a new server without storage/app/private/reports.
        Storage::disk('reports')->delete($export->storage_path);

        $this->actingAs($admin)->get(route('reporting.index'))
            ->assertOk()
            ->assertDontSee('Download CSV')
            ->assertSee('File missing');
        $this->actingAs($admin)->get(route('reporting.exports.download', $export))
            ->assertRedirect(route('reporting.index'))
            ->assertSessionHasErrors('export');
        $this->assertSame(0, $export->fresh()->download_count);
    }

    public function test_audit_search_is_admin_only_and_omits_sensitive_context(): void
    {
        $admin = $this->user(['admin']);
        $registrar = $this->user(['registrar']);
        $subject = StudentProfile::query()->create(['first_name' => 'Audit', 'last_name' => 'Subject']);
        $log = app(AuditRecorder::class)->record($admin, 'fictional.sensitive.action', $subject, ['private_reason' => 'DO-NOT-EXPOSE']);
        // Entries are append-only through the model (D-059); set the request context directly for this fixture.
        DB::table('audit_logs')->where('id', $log->id)->update(['ip_address' => '192.0.2.10', 'user_agent' => 'PRIVATE-USER-AGENT']);
        $index = $this->actingAs($admin)->get(route('reporting.audit.index', ['action' => 'fictional.sensitive']));
        $index->assertOk()->assertSee('fictional.sensitive.action')->assertSee($subject->public_id)->assertDontSee('DO-NOT-EXPOSE')->assertDontSee('192.0.2.10')->assertDontSee('PRIVATE-USER-AGENT');
        $detail = $this->actingAs($admin)->get(route('reporting.audit.show', $log));
        $detail->assertOk()->assertSee('fictional.sensitive.action')->assertDontSee('DO-NOT-EXPOSE')->assertDontSee('192.0.2.10')->assertDontSee('PRIVATE-USER-AGENT');
        $this->actingAs($registrar)->get(route('reporting.audit.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('reporting.audit.index', ['metadata' => 'private_reason']))->assertRedirect()->assertSessionHasErrors('filters');
    }

    public function test_information_updates_lists_only_self_profile_changes_for_registrar_and_admin(): void
    {
        $admin = $this->user(['admin']);
        $registrar = $this->user(['registrar']);
        $teacher = $this->user(['teacher']);
        $learner = $this->user(['student']);
        $learner->update(['name' => 'Selfupdate Learner']);
        $subject = StudentProfile::query()->create(['first_name' => 'Audit', 'last_name' => 'Subject']);
        $recorder = app(AuditRecorder::class);
        $recorder->record($learner, 'people.student.self_profile_updated', $subject, ['fields' => ['mobile']]);
        $recorder->record($admin, 'billing.payment.posted', $subject, ['private_reason' => 'NOT-A-PROFILE-UPDATE']);

        $this->get(route('reporting.information-updates.index'))->assertRedirect(route('identity.login'));
        $this->actingAs($teacher)->get(route('reporting.information-updates.index'))->assertForbidden();

        foreach ([$registrar, $admin] as $viewer) {
            $this->actingAs($viewer)->get(route('reporting.information-updates.index'))
                ->assertOk()
                ->assertSee('Information updates')
                ->assertDontSee('All Users Activity Log')
                ->assertSee('Selfupdate Learner')
                ->assertSee('Mobile number')
                ->assertDontSee('NOT-A-PROFILE-UPDATE')
                ->assertDontSee('billing.payment.posted');
        }

        $this->actingAs($registrar)->get(route('reporting.information-updates.index', ['account' => 'guardian']))
            ->assertOk()->assertDontSee('Selfupdate Learner');
        $this->actingAs($registrar)->get(route('reporting.information-updates.index', ['action' => 'billing']))
            ->assertRedirect()->assertSessionHasErrors('filters');
    }

    public function test_phase_eight_schema_and_private_disk_contract(): void
    {
        $this->assertTrue(Schema::hasTable('export_jobs'));
        $this->assertFalse((bool) config('filesystems.disks.reports.serve'));
        $this->assertSame('private', config('filesystems.disks.reports.visibility'));
        $indexes = collect(Schema::getIndexes('export_jobs'))->pluck('name');
        $this->assertTrue($indexes->contains(fn ($name) => str_contains($name, 'public_id')));
        $this->assertTrue($indexes->contains(fn ($name) => str_contains($name, 'requested_by')));
    }

    /** @return array{AcademicYear, GradeLevel, Section, Section} */
    private function catalogFixture(): array
    {
        $year = AcademicYear::query()->create(['code' => 'AY-'.Str::random(8), 'name' => 'Fictional 2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => 'active']);
        $grade = GradeLevel::query()->create(['code' => 'G-'.Str::random(8), 'name' => 'Grade 7', 'sequence' => random_int(100, 60000), 'is_active' => true]);
        $first = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Assigned '.Str::random(5), 'capacity' => 15, 'status' => 'active']);
        $second = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Unrelated '.Str::random(5), 'capacity' => 15, 'status' => 'active']);

        return [$year, $grade, $first, $second];
    }

    private function enrollmentFixture(AcademicYear $year, GradeLevel $grade, Section $section, User $actor, string $firstName, string $lastName): Enrollment
    {
        $student = StudentProfile::query()->create(['student_number' => 'STU-'.Str::random(8), 'first_name' => $firstName, 'last_name' => $lastName]);
        $enrollment = Enrollment::query()->create(['application_number' => 'APP-'.Str::random(10), 'student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'student_category' => 'regular', 'status' => 'enrolled', 'active_enrollment_key' => $student->id.':'.$year->id, 'submitted_at' => now()->subDay(), 'approved_at' => now(), 'enrolled_at' => now()]);
        SectionAssignment::query()->create(['enrollment_id' => $enrollment->id, 'section_id' => $section->id, 'status' => 'confirmed', 'active_assignment_key' => (string) $enrollment->id, 'confirmed_by' => $actor->id, 'confirmed_at' => now(), 'effective_from' => today()]);

        return $enrollment;
    }

    /** @param list<string> $roles */
    private function user(array $roles): User
    {
        $user = User::query()->create(['name' => 'Fictional '.Str::random(6), 'email' => Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
