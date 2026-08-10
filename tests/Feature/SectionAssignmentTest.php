<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Requirements\Models\DocumentType;
use App\Modules\SectionAssignment\Actions\ConfirmSectionAssignmentAction;
use App\Modules\SectionAssignment\Actions\CreatePromotionDraftAction;
use App\Modules\SectionAssignment\Actions\RecommendPromotionAction;
use App\Modules\SectionAssignment\Actions\RecommendSectionAction;
use App\Modules\SectionAssignment\Actions\TransferSectionAssignmentAction;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\StudentSupport\Models\ApprovedSupportSummary;
use App\Modules\StudentSupport\Models\SupportAssessment;
use App\Modules\StudentSupport\Models\SupportCase;
use App\Modules\StudentSupport\Models\SupportObservation;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SectionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    private AcademicYear $futureYear;

    private GradeLevel $grade;

    private GradeLevel $nextGrade;

    private Section $faith;

    private Section $hope;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->year = AcademicYear::query()->create(['code' => 'P5-2026', 'name' => 'Fictional 2026', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => 'open']);
        $this->futureYear = AcademicYear::query()->create(['code' => 'P5-2027', 'name' => 'Fictional 2027', 'starts_on' => '2027-06-01', 'ends_on' => '2028-03-31', 'status' => 'planned']);
        $this->nextGrade = GradeLevel::query()->create(['code' => 'P5-G2', 'name' => 'Fictional Grade 2', 'sequence' => 2, 'is_active' => true]);
        $this->grade = GradeLevel::query()->create(['code' => 'P5-G1', 'name' => 'Fictional Grade 1', 'sequence' => 1, 'next_grade_level_id' => $this->nextGrade->id, 'is_active' => true]);
        $this->faith = Section::query()->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade->id, 'name' => 'Faith', 'capacity' => 2, 'sort_order' => 1, 'status' => 'active']);
        $this->hope = Section::query()->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade->id, 'name' => 'Hope', 'capacity' => 2, 'sort_order' => 2, 'status' => 'active']);
    }

    public function test_recommendation_is_deterministic_and_excludes_ineligible_or_full_sections(): void
    {
        $approved = $this->enrollment('approved');
        $earlier = $this->enrollment('approved');
        $earlier->update(['approved_at' => now()->subDay()]);
        Section::query()->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade->id, 'name' => 'Inactive', 'capacity' => 10, 'sort_order' => 0, 'status' => 'inactive']);
        $first = app(RecommendSectionAction::class)->execute($approved);
        $this->assertSame($this->faith->id, $first->section?->id);

        $this->assignment($this->enrollment('enrolled'), $this->faith);
        $next = app(RecommendSectionAction::class)->execute($approved);
        $this->assertSame($this->hope->id, $next->section?->id);
        $this->assignment($this->enrollment('enrolled'), $this->hope);
        $this->assignment($this->enrollment('enrolled'), $this->faith);
        $this->assignment($this->enrollment('enrolled'), $this->hope);
        $this->assertNull(app(RecommendSectionAction::class)->execute($approved)->section);
        $registrar = $this->user(['registrar']);
        $response = $this->actingAs($registrar)->get(route('section-assignment.index'));
        $response->assertOk()->assertSeeInOrder([$earlier->student->full_name, $approved->student->full_name])->assertSee('Assign section');
        $this->assertSame(3, substr_count($response->getContent(), '<dialog'));

        $this->expectException(ValidationException::class);
        app(RecommendSectionAction::class)->execute($this->enrollment('submitted'));
    }

    public function test_confirmation_finalizes_academic_enrollment_and_capacity_override_is_admin_only(): void
    {
        $this->faith->update(['capacity' => 1]);
        $registrar = $this->user(['registrar']);
        $admin = $this->user(['admin']);
        $accounting = $this->user(['accounting']);
        $teacher = $this->user(['teacher']);
        $first = $this->enrollment('approved');
        $this->get(route('section-assignment.index'))->assertRedirect(route('identity.login'));
        $assignment = app(ConfirmSectionAssignmentAction::class)->execute($first, $this->faith, $registrar, false);

        $this->assertSame('enrolled', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->enrolled_at);
        $this->assertSame($registrar->id, $assignment->confirmed_by);
        $this->assertDatabaseHas('enrollment_status_history', ['enrollment_id' => $first->id, 'from_status' => 'approved', 'to_status' => 'enrolled']);

        $second = $this->enrollment('approved');
        $this->actingAs($registrar)->post(route('section-assignment.confirm', $second), ['section_public_id' => $this->faith->public_id, 'override_capacity' => 1, 'override_reason' => 'Not allowed'])->assertForbidden();
        $this->actingAs($accounting)->post(route('section-assignment.confirm', $second), ['section_public_id' => $this->faith->public_id])->assertForbidden();
        $this->actingAs($teacher)->post(route('section-assignment.confirm', $second), ['section_public_id' => $this->faith->public_id])->assertForbidden();
        $this->actingAs($admin)->post(route('section-assignment.confirm', $second), ['section_public_id' => $this->faith->public_id, 'override_capacity' => 1])->assertSessionHasErrors('override_reason');
        $this->actingAs($admin)->post(route('section-assignment.confirm', $second), ['section_public_id' => $this->faith->public_id, 'override_capacity' => 1, 'override_reason' => 'Fictional approved capacity exception'])->assertRedirect();

        $this->assertDatabaseHas('section_assignments', ['enrollment_id' => $second->id, 'is_capacity_override' => true, 'capacity_override_by' => $admin->id]);
        $this->assertDatabaseHas('section_assignment_events', ['enrollment_id' => $second->id, 'event_type' => 'confirmed_override', 'is_capacity_override' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'section_assignment.confirmed', 'actor_user_id' => $admin->id]);

        $this->hope->update(['capacity' => 1]);
        $this->assignment($this->enrollment('enrolled'), $this->hope);
        $this->actingAs($registrar)->post(route('section-assignment.transfer', $assignment), ['section_public_id' => $this->hope->public_id, 'reason' => 'Registrar attempt', 'override_capacity' => 1, 'override_reason' => 'Not authorized'])->assertForbidden();
        $this->actingAs($admin)->post(route('section-assignment.transfer', $assignment), ['section_public_id' => $this->hope->public_id, 'reason' => 'Approved exceptional transfer', 'override_capacity' => 1])->assertSessionHasErrors('override_reason');
        $this->actingAs($admin)->post(route('section-assignment.transfer', $assignment), ['section_public_id' => $this->hope->public_id, 'reason' => 'Approved exceptional transfer', 'override_capacity' => 1, 'override_reason' => 'Fictional transfer capacity exception'])->assertRedirect();
        $this->assertDatabaseHas('section_assignment_events', ['enrollment_id' => $first->id, 'event_type' => 'transferred_override', 'is_capacity_override' => true]);
        $this->actingAs($admin)->get(route('section-assignment.index'))->assertOk()->assertSee('Fictional transfer capacity exception')->assertSee('Approved exceptional transfer');
    }

    public function test_support_review_requires_approved_outcome_and_explicit_suitability(): void
    {
        $registrar = $this->user(['registrar']);
        $enrollment = $this->enrollment('approved', 'support_review');

        try {
            app(ConfirmSectionAssignmentAction::class)->execute($enrollment, $this->faith, $registrar, false);
            $this->fail('Support placement must require an approved outcome and suitability.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        SupportCase::query()->create(['enrollment_id' => $enrollment->id, 'status' => 'reviewed', 'opened_by' => $registrar->id, 'opened_at' => now(), 'assigned_reviewer_id' => $registrar->id, 'final_outcome' => 'approved']);
        $this->expectException(ValidationException::class);
        app(ConfirmSectionAssignmentAction::class)->execute($enrollment, $this->faith, $registrar, false);
    }

    public function test_suitable_support_student_can_be_confirmed_and_transferred_with_history(): void
    {
        $registrar = $this->user(['registrar']);
        $enrollment = $this->enrollment('approved', 'support_review');
        SupportCase::query()->create(['enrollment_id' => $enrollment->id, 'status' => 'reviewed', 'opened_by' => $registrar->id, 'opened_at' => now(), 'assigned_reviewer_id' => $registrar->id, 'final_outcome' => 'approved']);
        $first = app(ConfirmSectionAssignmentAction::class)->execute($enrollment, $this->faith, $registrar, true);
        $this->actingAs($registrar)->post(route('section-assignment.transfer', $first), ['section_public_id' => $this->hope->public_id])->assertSessionHasErrors('reason');
        $second = app(TransferSectionAssignmentAction::class)->execute($first, $this->hope, $registrar, 'Fictional balancing request', true);

        $this->assertSame('ended', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->ended_at);
        $this->assertSame($first->id, $second->transferred_from_id);
        $this->assertSame($this->hope->id, $enrollment->fresh()->currentSectionAssignment->section_id);
        $this->assertDatabaseHas('section_assignment_events', ['section_assignment_id' => $second->id, 'event_type' => 'transferred', 'from_section_id' => $this->faith->id, 'to_section_id' => $this->hope->id]);
        $this->actingAs($registrar)->get(route('section-assignment.index'))->assertOk()->assertSee('Fictional balancing request');
    }

    public function test_teacher_summary_access_tracks_active_section_assignment_and_hides_raw_data(): void
    {
        $registrar = $this->user(['registrar']);
        $teacherFaith = $this->teacher('Teacher Faith');
        $teacherHope = $this->teacher('Teacher Hope');
        $unrelated = $this->teacher('Teacher Unrelated');
        TeachingAssignment::query()->create(['staff_profile_id' => $teacherFaith->staffProfile->id, 'section_id' => $this->faith->id, 'assignment_type' => 'adviser', 'is_active' => true]);
        TeachingAssignment::query()->create(['staff_profile_id' => $teacherHope->staffProfile->id, 'section_id' => $this->hope->id, 'assignment_type' => 'adviser', 'is_active' => true]);
        TeachingAssignment::query()->create(['staff_profile_id' => $unrelated->staffProfile->id, 'section_id' => $this->faith->id, 'assignment_type' => 'subject_teacher', 'is_active' => false]);

        $enrollment = $this->enrollment('approved', 'support_review');
        $case = SupportCase::query()->create(['enrollment_id' => $enrollment->id, 'status' => 'reviewed', 'opened_by' => $registrar->id, 'opened_at' => now(), 'assigned_reviewer_id' => $registrar->id, 'final_outcome' => 'approved']);
        SupportAssessment::query()->create(['support_case_id' => $case->id, 'reviewer_id' => $registrar->id, 'outcome' => 'approved', 'confidential_rationale' => 'Restricted rationale must remain hidden.']);
        SupportObservation::query()->create(['support_case_id' => $case->id, 'body' => 'Restricted observation must remain hidden.', 'author_id' => $registrar->id]);
        ApprovedSupportSummary::query()->create(['support_case_id' => $case->id, 'summary' => 'Draft summary must remain hidden.']);
        ApprovedSupportSummary::query()->create(['support_case_id' => $case->id, 'summary' => 'Use short, clear classroom directions.', 'approved_by' => $registrar->id, 'approved_at' => now(), 'effective_from' => today()]);
        $first = app(ConfirmSectionAssignmentAction::class)->execute($enrollment, $this->faith, $registrar, true);

        $this->actingAs($teacherFaith)->get(route('teacher.students.support-summary', $enrollment->student))->assertOk()->assertSee('Use short, clear classroom directions.')->assertDontSee('Draft summary')->assertDontSee('Restricted rationale')->assertDontSee('Restricted observation')->assertDontSee('final_outcome')->assertDontSee('assigned_reviewer_id');
        $this->actingAs($teacherFaith)->get(route('academics.teacher.assignments'))->assertOk()->assertSee($enrollment->student->full_name);
        $this->actingAs($teacherFaith)->get(route('support.show', $case))->assertNotFound();
        $this->actingAs($teacherHope)->get(route('teacher.students.support-summary', $enrollment->student))->assertNotFound();
        $this->actingAs($unrelated)->get(route('teacher.students.support-summary', $enrollment->student))->assertNotFound();
        $this->actingAs($teacherFaith)->get(route('teacher.students.support-summary', (string) Str::ulid()))->assertNotFound();
        $this->assertDatabaseHas('support_access_logs', ['support_case_id' => $case->id, 'actor_user_id' => $teacherFaith->id, 'action' => 'teacher_safe_summary_view']);

        app(TransferSectionAssignmentAction::class)->execute($first, $this->hope, $registrar, 'Fictional section balance', true);
        $this->actingAs($teacherFaith)->get(route('teacher.students.support-summary', $enrollment->student))->assertNotFound();
        $this->actingAs($teacherHope)->get(route('teacher.students.support-summary', $enrollment->student))->assertOk()->assertSee('Use short, clear classroom directions.');
    }

    public function test_guardian_and_student_portals_show_only_current_confirmed_section(): void
    {
        $registrar = $this->user(['registrar']);
        $guardianUser = $this->user(['guardian']);
        $otherGuardian = $this->user(['guardian']);
        $studentUser = $this->user(['student']);
        $otherStudentUser = $this->user(['student']);
        $studentUser->update(['name' => 'Portal Student']);
        $otherStudentUser->update(['name' => 'Other Student']);
        $guardian = GuardianProfile::query()->create(['user_id' => $guardianUser->id, 'first_name' => 'Linked', 'last_name' => 'Guardian']);
        GuardianProfile::query()->create(['user_id' => $otherGuardian->id, 'first_name' => 'Other', 'last_name' => 'Guardian']);
        $student = StudentProfile::query()->create(['user_id' => $studentUser->id, 'first_name' => 'Portal', 'last_name' => 'Student']);
        $otherStudent = StudentProfile::query()->create(['user_id' => $otherStudentUser->id, 'first_name' => 'Other', 'last_name' => 'Student']);
        $enrollment = $this->enrollment('approved', 'regular', $student, $guardian);
        $this->enrollment('approved', 'regular', $otherStudent);
        app(ConfirmSectionAssignmentAction::class)->execute($enrollment, $this->faith, $registrar, false);

        $this->actingAs($guardianUser)->get(route('guardian.enrollment.progress'))->assertOk()->assertSee('Confirmed section: Faith')->assertDontSee('Capacity override');
        $this->actingAs($guardianUser)->get(route('portal.section.print', $enrollment))->assertOk()->assertSee('Confirmed section and schedule')->assertSee('images/reporting/jca-reports-header.png')->assertSee('Section Faith')->assertDontSee('Capacity override')->assertDontSee('Restricted rationale');
        $this->actingAs($otherGuardian)->get(route('guardian.enrollment.progress'))->assertOk()->assertDontSee('Portal Student')->assertDontSee('Confirmed section: Faith');
        $this->actingAs($otherGuardian)->get(route('portal.section.print', $enrollment))->assertNotFound();
        $this->actingAs($studentUser)->get(route('student.enrollment.progress'))->assertOk()->assertSee('Confirmed section: Faith')->assertDontSee('Capacity override');
        $this->actingAs($studentUser)->get(route('portal.section.print', $enrollment))->assertOk()->assertSee('Section Faith');
        $current = $enrollment->fresh()->currentSectionAssignment;
        app(TransferSectionAssignmentAction::class)->execute($current, $this->hope, $registrar, 'Portal update transfer', false);
        $this->actingAs($guardianUser)->get(route('guardian.enrollment.progress'))->assertOk()->assertSee('Confirmed section: Hope')->assertDontSee('Confirmed section: Faith');
        $this->actingAs($studentUser)->get(route('student.enrollment.progress'))->assertOk()->assertSee('Confirmed section: Hope')->assertDontSee('Confirmed section: Faith');
        $this->actingAs($otherStudentUser)->get(route('student.enrollment.progress'))->assertOk()->assertDontSee('Portal Student')->assertDontSee('Confirmed section: Hope');
        auth()->logout();
        $this->get(route('student.enrollment.progress'))->assertRedirect(route('identity.login'));
    }

    public function test_promotion_creates_one_predecessor_linked_draft_without_mutating_history(): void
    {
        $registrar = $this->user(['registrar']);
        $teacher = $this->user(['teacher']);
        $accounting = $this->user(['accounting']);
        $source = $this->enrollment('enrolled');
        $birthCertificate = DocumentType::query()->create(['code' => 'P5-BIRTH', 'name' => 'Fictional Birth Certificate']);
        $waivedDocument = DocumentType::query()->create(['code' => 'P5-WAIVED', 'name' => 'Fictional Waived Document']);
        $sourceRequirement = EnrollmentRequirement::query()->create([
            'enrollment_id' => $source->id,
            'document_type_id' => $birthCertificate->id,
            'rule_snapshot' => ['is_required' => true, 'is_blocking' => true, 'name' => $birthCertificate->name],
            'status' => 'verified',
            'received_by' => $registrar->id,
            'received_at' => now()->subMonth(),
            'receipt_notes' => 'Original inspected once.',
            'reviewed_by' => $registrar->id,
            'reviewed_at' => now()->subMonth(),
        ]);
        EnrollmentRequirement::query()->create([
            'enrollment_id' => $source->id,
            'document_type_id' => $waivedDocument->id,
            'rule_snapshot' => ['is_required' => true, 'is_blocking' => true, 'name' => $waivedDocument->name],
            'status' => 'waived',
            'waived_by' => $registrar->id,
        ]);
        $this->post(route('promotion.recommend', $source), ['target_academic_year_public_id' => $this->futureYear->public_id])->assertRedirect(route('identity.login'));
        $this->actingAs($teacher)->post(route('promotion.recommend', $source), ['target_academic_year_public_id' => $this->futureYear->public_id])->assertForbidden();
        $this->actingAs($accounting)->post(route('promotion.recommend', $source), ['target_academic_year_public_id' => $this->futureYear->public_id])->assertForbidden();
        $recommendation = app(RecommendPromotionAction::class)->execute($source, $registrar, $this->futureYear);
        $this->assertSame('eligible', $recommendation->eligibility_status);
        $draft = app(CreatePromotionDraftAction::class)->execute($recommendation, $registrar);

        $this->assertSame('draft', $draft->status);
        $this->assertSame($source->id, $draft->previous_enrollment_id);
        $this->assertSame($this->futureYear->id, $draft->academic_year_id);
        $this->assertSame($this->nextGrade->id, $draft->grade_level_id);
        $this->assertSame('enrolled', $source->fresh()->status);
        $this->assertSame($this->grade->id, $source->fresh()->grade_level_id);

        $carried = $draft->requirements()->with('carriedFromRequirement')->firstOrFail();
        $this->assertSame($sourceRequirement->id, $carried->carried_from_requirement_id);
        $this->assertSame('verified', $carried->status);
        $this->assertSame($sourceRequirement->received_at->toDateTimeString(), $carried->received_at->toDateTimeString());
        $this->assertSame($registrar->id, $carried->carried_over_by);
        $this->assertDatabaseCount('enrollment_requirements', 3);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'requirements.hard_copy.carried_forward',
            'actor_user_id' => $registrar->id,
            'subject_id' => $carried->id,
        ]);

        app(TransitionEnrollmentAction::class)->execute($draft, 'submitted', $registrar);
        app(TransitionEnrollmentAction::class)->execute($draft->fresh(), 'under_review', $registrar);
        $this->actingAs($registrar)
            ->get(route('enrollment.registrar.index', ['status' => 'under_review']))
            ->assertOk()
            ->assertSee('Carried forward')
            ->assertSee('No resubmission is required');
        app(TransitionEnrollmentAction::class)->execute($draft->fresh(), 'approved', $registrar);
        $this->assertSame('approved', $draft->fresh()->status);

        $this->expectException(ValidationException::class);
        app(CreatePromotionDraftAction::class)->execute($recommendation->fresh(), $registrar);
    }

    public function test_terminal_grade_is_recorded_as_ineligible_and_schema_constraints_preserve_history(): void
    {
        $registrar = $this->user(['registrar']);
        $terminal = GradeLevel::query()->create(['code' => 'P5-TERM', 'name' => 'Fictional Terminal', 'sequence' => 3, 'is_active' => true]);
        $source = $this->enrollment('enrolled', 'regular', null, null, $terminal);
        $recommendation = app(RecommendPromotionAction::class)->execute($source, $registrar, $this->futureYear);
        $this->assertSame('ineligible', $recommendation->eligibility_status);
        $this->assertNull($recommendation->target_grade_level_id);

        $assignment = $this->assignment($this->enrollment('enrolled'), $this->faith);
        $indexes = collect(Schema::getIndexes('section_assignments'))->pluck('name');
        $this->assertContains('section_assignment_capacity_idx', $indexes);
        $this->assertContains('section_assignment_current_idx', $indexes);
        try {
            SectionAssignment::query()->create(['enrollment_id' => $assignment->enrollment_id, 'section_id' => $this->hope->id, 'status' => 'confirmed', 'active_assignment_key' => (string) $assignment->enrollment_id, 'confirmed_by' => $registrar->id, 'confirmed_at' => now(), 'effective_from' => today()]);
            $this->fail('MySQL must reject a duplicate active assignment key.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        $this->expectException(QueryException::class);
        $this->faith->delete();
    }

    public function test_workspace_uses_server_filtered_independently_paginated_tables_and_shared_action_modals(): void
    {
        $registrar = $this->user(['registrar']);
        $otherGrade = GradeLevel::query()->create(['code' => 'P5-G9', 'name' => 'Fictional Grade 9', 'sequence' => 9, 'is_active' => true]);

        for ($index = 1; $index <= 18; $index++) {
            $student = StudentProfile::query()->create(['first_name' => 'Scale', 'last_name' => sprintf('Approved%02d', $index)]);
            $this->enrollment('approved', 'regular', $student);
        }

        for ($index = 1; $index <= 16; $index++) {
            $student = StudentProfile::query()->create(['first_name' => 'Scale', 'last_name' => sprintf('Assigned%02d', $index)]);
            $this->assignment($this->enrollment('enrolled', 'regular', $student), $this->faith);
        }

        for ($index = 1; $index <= 16; $index++) {
            $student = StudentProfile::query()->create(['first_name' => 'Scale', 'last_name' => sprintf('History%02d', $index)]);
            $assignment = $this->assignment($this->enrollment('enrolled', 'regular', $student), $this->hope);
            $assignment->update([
                'status' => 'ended',
                'active_assignment_key' => null,
                'ended_at' => now(),
                'ended_reason' => 'Historical scale test transfer.',
            ]);
        }

        $excludedStudent = StudentProfile::query()->create(['first_name' => 'Excluded', 'last_name' => 'DifferentGrade']);
        $this->enrollment('approved', 'regular', $excludedStudent, null, $otherGrade);

        $response = $this->actingAs($registrar)->get(route('section-assignment.index', [
            'grade' => $this->grade->public_id,
            'per_page' => 15,
        ]));

        $response
            ->assertOk()
            ->assertSee('Find students and assignments')
            ->assertSee('Active filters')
            ->assertSee('Grade: '.$this->grade->name)
            ->assertDontSee('Excluded DifferentGrade')
            ->assertViewHas('approvedEnrollments', fn ($rows): bool => $rows->total() === 18 && $rows->count() === 15 && $rows->lastPage() === 2)
            ->assertViewHas('currentAssignments', fn ($rows): bool => $rows->total() === 16 && $rows->count() === 15 && $rows->lastPage() === 2)
            ->assertViewHas('assignmentHistory', fn ($rows): bool => $rows->total() === 16 && $rows->count() === 15 && $rows->lastPage() === 2)
            ->assertViewHas('enrolledForPromotion', fn ($rows): bool => $rows->total() === 32 && $rows->count() === 15 && $rows->lastPage() === 3);

        $this->assertSame(3, substr_count($response->getContent(), '<dialog'));
        $this->assertSame(45, substr_count($response->getContent(), 'aria-haspopup="dialog"'));

        $searchResponse = $this->actingAs($registrar)->get(route('section-assignment.index', [
            'search' => 'Scale Approved18',
            'grade' => $this->grade->public_id,
            'per_page' => 15,
        ]));
        $searchResponse
            ->assertOk()
            ->assertSee('Scale Approved18')
            ->assertViewHas('approvedEnrollments', fn ($rows): bool => $rows->total() === 1)
            ->assertViewHas('currentAssignments', fn ($rows): bool => $rows->total() === 0)
            ->assertViewHas('assignmentHistory', fn ($rows): bool => $rows->total() === 0)
            ->assertViewHas('enrolledForPromotion', fn ($rows): bool => $rows->total() === 0);

        $this->actingAs($registrar)
            ->from(route('section-assignment.index'))
            ->get(route('section-assignment.index', ['promotion_status' => 'promote_everyone', 'per_page' => 500]))
            ->assertRedirect(route('section-assignment.index'))
            ->assertSessionHasErrors(['promotion_status', 'per_page']);
    }

    private function enrollment(string $status, string $category = 'regular', ?StudentProfile $student = null, ?GuardianProfile $guardian = null, ?GradeLevel $grade = null): Enrollment
    {
        $student ??= StudentProfile::query()->create(['first_name' => 'Fictional', 'last_name' => Str::random(8)]);
        $guardian ??= GuardianProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Guardian']);
        $grade ??= $this->grade;

        return Enrollment::query()->create(['application_number' => 'P5-'.Str::upper(Str::random(10)), 'student_id' => $student->id, 'academic_year_id' => $this->year->id, 'grade_level_id' => $grade->id, 'applicant_guardian_id' => $guardian->id, 'student_category' => $category, 'status' => $status, 'active_enrollment_key' => $student->id.':'.$this->year->id, 'contact_verified_at' => now(), 'approved_at' => in_array($status, ['approved', 'enrolled'], true) ? now() : null, 'enrolled_at' => $status === 'enrolled' ? now() : null]);
    }

    private function assignment(Enrollment $enrollment, Section $section): SectionAssignment
    {
        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('code', 'registrar'))->first() ?? $this->user(['registrar']);

        return SectionAssignment::query()->create(['enrollment_id' => $enrollment->id, 'section_id' => $section->id, 'status' => 'confirmed', 'active_assignment_key' => (string) $enrollment->id, 'confirmed_by' => $actor->id, 'confirmed_at' => now(), 'effective_from' => today()]);
    }

    private function teacher(string $name): User
    {
        $user = $this->user(['teacher']);
        $user->update(['name' => $name]);
        StaffProfile::query()->create(['user_id' => $user->id, 'active_from' => today()]);

        return $user->fresh('staffProfile');
    }

    /** @param list<string> $roles */
    private function user(array $roles): User
    {
        $user = User::query()->create(['name' => 'Fictional '.Str::random(6), 'email' => Str::lower(Str::random(10)).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
