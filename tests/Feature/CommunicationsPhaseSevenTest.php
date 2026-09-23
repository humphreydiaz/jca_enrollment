<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Communications\Actions\QueueSmsNotificationAction;
use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Enums\SmsDeliveryRoute;
use App\Modules\Communications\Gateways\FakeSmsGateway;
use App\Modules\Communications\Gateways\SemaphoreSmsGateway;
use App\Modules\Communications\Jobs\DispatchSmsNotification;
use App\Modules\Communications\Models\Announcement;
use App\Modules\Communications\Models\AnnouncementAudience;
use App\Modules\Communications\Models\Notification;
use App\Modules\Communications\Models\NotificationTemplate;
use App\Modules\Communications\Models\SmsConsent;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\Shared\Audit\AuditRecorder;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunicationsPhaseSevenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_portals_and_announcements_are_scoped_to_the_authorized_audience(): void
    {
        $admin = $this->user(['admin']);
        $guardian = $this->user(['guardian']);
        $otherGuardian = $this->user(['guardian']);
        GuardianProfile::query()->create(['user_id' => $otherGuardian->id, 'first_name' => 'Other', 'last_name' => 'Guardian']);
        $studentUser = $this->user(['student']);
        $teacher = $this->user(['teacher']);
        [$student, $enrollment, $section] = $this->studentFixture($guardian, $studentUser);
        $teacherProfile = StaffProfile::query()->create(['user_id' => $teacher->id]);
        TeachingAssignment::query()->create(['staff_profile_id' => $teacherProfile->id, 'section_id' => $section->id, 'assignment_type' => 'adviser', 'is_active' => true]);

        $all = $this->announcement($admin, 'All portal notice', 'all');
        $guardianOnly = $this->announcement($admin, 'Guardian notice', 'guardian', guardianId: $guardian->guardianProfile->id);
        $teacherOnly = $this->announcement($admin, 'Section notice', 'section', sectionId: $section->id);
        $studentOnly = $this->announcement($admin, 'Student notice', 'student', studentId: $student->id);

        $this->actingAs($guardian)->get(route('identity.dashboard'))->assertOk()->assertSee('My children')->assertSee('All portal notice')->assertSee('Guardian notice')->assertSee('Section notice')->assertDontSee('Student notice');
        $this->actingAs($otherGuardian)->get(route('communications.index'))->assertOk()->assertSee('All portal notice')->assertDontSee('Guardian notice');
        $this->actingAs($studentUser)->get(route('communications.index'))->assertOk()->assertSee('Student notice')->assertDontSee('Guardian notice');
        $this->actingAs($teacher)->get(route('communications.index'))->assertOk()->assertSee('Section notice')->assertDontSee('Student notice');
        $this->actingAs($otherGuardian)->get(route('communications.show', $guardianOnly))->assertNotFound();
        $this->actingAs($teacher)->get(route('communications.show', $studentOnly))->assertNotFound();
        $this->actingAs($admin)->get(route('communications.show', $guardianOnly))->assertOk();
        $this->actingAs($otherGuardian)->get(route('guardian.enrollment.progress'))->assertOk()->assertDontSee($enrollment->application_number);
        $this->assertDatabaseHas('audit_logs', ['action' => 'communications.announcement.viewed', 'subject_id' => $guardianOnly->id]);
        $this->assertNotNull($all);
    }

    public function test_sms_requires_consent_is_idempotent_and_never_uses_live_network_in_fake_mode(): void
    {
        $admin = $this->user(['admin']);
        $guardianUser = $this->user(['guardian']);
        [$student] = $this->studentFixture($guardianUser);
        $guardian = $guardianUser->guardianProfile;
        $guardian->update(['mobile' => '09171234567']);
        $template = NotificationTemplate::query()->create(['code' => 'enrollment_status', 'channel' => 'sms', 'purpose' => 'transactional', 'version' => 1, 'content' => '{{school_name}}: your enrollment status is {{status}}. {{action_url}}', 'is_active' => true, 'created_by' => $admin->id]);
        $action = app(QueueSmsNotificationAction::class);
        $this->assertNull($action->execute($template, $guardian, 'status:'.$student->id, ['school_name' => 'JCA', 'status' => 'approved', 'action_url' => 'https://example.test/progress', 'announcement_title' => '', 'announcement_body' => '']));
        SmsConsent::query()->create(['guardian_id' => $guardian->id, 'purpose' => 'transactional', 'status' => 'granted', 'source' => 'fictional-test', 'effective_at' => now(), 'recorded_by' => $guardianUser->id]);
        $notification = $action->execute($template, $guardian, 'status:'.$student->id, ['school_name' => 'JCA', 'status' => 'approved', 'action_url' => 'https://example.test/progress', 'announcement_title' => '', 'announcement_body' => '']);
        $this->assertNotNull($notification);
        $this->assertSame($notification->id, $action->execute($template, $guardian, 'status:'.$student->id, ['school_name' => 'JCA', 'status' => 'approved', 'action_url' => 'https://example.test/progress', 'announcement_title' => '', 'announcement_body' => ''])->id);

        $fake = new FakeSmsGateway;
        app()->instance(SmsGateway::class, $fake);
        (new DispatchSmsNotification($notification->id))->handle($fake, app(AuditRecorder::class));
        (new DispatchSmsNotification($notification->id))->handle($fake, app(AuditRecorder::class));
        $this->assertCount(1, $fake->sent);
        $this->assertSame('accepted', $notification->fresh()->status);
        $this->assertDatabaseHas('sms_messages', ['notification_id' => $notification->id, 'attempt_count' => 1, 'status' => 'accepted']);

        $failed = $action->execute($template, $guardian, 'status-failed:'.$student->id, ['school_name' => 'JCA', 'status' => 'waitlisted', 'action_url' => 'https://example.test/progress', 'announcement_title' => '', 'announcement_body' => '']);
        $fake->failNext = true;
        (new DispatchSmsNotification($failed->id))->handle($fake, app(AuditRecorder::class));
        $this->assertSame('failed', $failed->fresh()->status);
        $this->actingAs($admin)->post(route('communications.notifications.retry', $failed))->assertRedirect();
        (new DispatchSmsNotification($failed->id))->handle($fake, app(AuditRecorder::class));
        $this->assertSame('accepted', $failed->fresh()->status);

        config(['communications.sms.enabled' => true, 'communications.sms.driver' => 'semaphore', 'communications.sms.semaphore.api_key' => 'fictional-key', 'communications.sms.semaphore.sender_name' => 'JCA']);
        Http::fake([
            config('communications.sms.semaphore.base_url').'/api/v4/messages' => Http::response([['message_id' => 'sem-standard', 'status' => 'Queued']], 200),
            config('communications.sms.semaphore.base_url').'/api/v4/priority' => Http::response([['message_id' => 'sem-priority', 'status' => 'Pending']], 200),
            config('communications.sms.semaphore.base_url').'/api/v4/otp' => Http::response([['message_id' => 'sem-otp', 'status' => 'Pending', 'code' => 'ABCD-EFGH-JKLM-NPQR']], 200),
        ]);
        $result = (new SemaphoreSmsGateway)->send('09171234567', 'JCA: enrollment status updated.', 'fake-key');
        $this->assertTrue($result->accepted());
        $this->assertSame('sem-standard', $result->providerReference);

        $priorityResult = (new SemaphoreSmsGateway)->send('09171234567', 'JCA verification code: 123456.', 'priority-key', SmsDeliveryRoute::Priority);
        $this->assertTrue($priorityResult->accepted());
        $this->assertSame('sem-priority', $priorityResult->providerReference);
        $this->assertSame('pending', $priorityResult->providerStatus);

        $otpResult = (new SemaphoreSmsGateway)->send('09171234567', 'JCSGOCA account code: {otp}.', 'otp-key', SmsDeliveryRoute::Otp, 'ABCD-EFGH-JKLM-NPQR');
        $this->assertTrue($otpResult->accepted());
        $this->assertSame('sem-otp', $otpResult->providerReference);
        Http::assertSent(fn ($request) => $request->url() === config('communications.sms.semaphore.base_url').'/api/v4/messages' && $request['sendername'] === 'JCA' && $request['apikey'] === 'fictional-key');
        Http::assertSent(fn ($request) => $request->url() === config('communications.sms.semaphore.base_url').'/api/v4/priority' && $request['sendername'] === 'JCA' && $request['apikey'] === 'fictional-key');
        Http::assertSent(fn ($request) => $request->url() === config('communications.sms.semaphore.base_url').'/api/v4/otp'
            && $request['sendername'] === 'JCA'
            && $request['message'] === 'JCSGOCA account code: {otp}.'
            && $request['code'] === 'ABCD-EFGH-JKLM-NPQR');

    }

    public function test_semaphore_rejects_an_invalid_otp_request_before_calling_the_provider(): void
    {
        config(['communications.sms.enabled' => true, 'communications.sms.driver' => 'semaphore', 'communications.sms.semaphore.api_key' => 'fictional-key', 'communications.sms.semaphore.sender_name' => 'JCA']);
        Http::fake();

        $result = (new SemaphoreSmsGateway)->send('09171234567', 'Missing the required placeholder.', 'otp-key', SmsDeliveryRoute::Otp, 'ABCD-EFGH-JKLM-NPQR');

        $this->assertFalse($result->accepted());
        $this->assertSame('invalid_otp_request', $result->failureCode);
        Http::assertNothingSent();
    }

    public function test_semaphore_provider_failure_status_is_not_recorded_as_an_accepted_send(): void
    {
        config(['communications.sms.enabled' => true, 'communications.sms.driver' => 'semaphore', 'communications.sms.semaphore.api_key' => 'fictional-key', 'communications.sms.semaphore.sender_name' => 'JCA']);
        Http::fake([config('communications.sms.semaphore.base_url').'/api/v4/priority' => Http::response([['message_id' => 'sem-failed', 'status' => 'Failed']], 200)]);

        $result = (new SemaphoreSmsGateway)->send('09171234567', 'JCA verification code: 123456.', 'failed-key', SmsDeliveryRoute::Priority);

        $this->assertFalse($result->accepted());
        $this->assertSame('semaphore_status_failed', $result->failureCode);
    }

    public function test_semaphore_retries_one_transient_provider_failure(): void
    {
        config(['communications.sms.enabled' => true, 'communications.sms.driver' => 'semaphore', 'communications.sms.semaphore.api_key' => 'fictional-key', 'communications.sms.semaphore.sender_name' => 'JCA']);
        Http::fakeSequence()
            ->push(['message' => 'Temporary provider failure'], 503)
            ->push([['message_id' => 'sem-recovered', 'status' => 'Queued']], 200);

        $result = (new SemaphoreSmsGateway)->send('09171234567', 'JCA verification code: 123456.', 'retry-key', SmsDeliveryRoute::Priority);

        $this->assertTrue($result->accepted());
        $this->assertSame('sem-recovered', $result->providerReference);
        Http::assertSentCount(2);
    }

    public function test_default_disabled_driver_records_no_live_delivery_and_each_staff_portal_has_safe_next_actions(): void
    {
        $admin = $this->user(['admin']);
        $registrar = $this->user(['registrar']);
        $accounting = $this->user(['accounting']);
        $teacher = $this->user(['teacher']);
        $guardian = $this->user(['guardian']);
        $student = $this->user(['student']);
        foreach ([[$admin, 'Enrollment review'], [$registrar, 'Applications'], [$accounting, 'Billing work'], [$teacher, 'My students'], [$guardian, 'My children'], [$student, 'My enrollment']] as [$user, $card]) {
            $this->actingAs($user)->get(route('identity.dashboard'))->assertOk()->assertSee($card);
        }

        $guardianProfile = GuardianProfile::query()->create(['user_id' => $guardian->id, 'first_name' => 'Disabled', 'last_name' => 'Recipient', 'mobile' => '09170000000']);
        SmsConsent::query()->create(['guardian_id' => $guardianProfile->id, 'purpose' => 'transactional', 'status' => 'granted', 'source' => 'fictional-test', 'effective_at' => now(), 'recorded_by' => $guardian->id]);
        $template = NotificationTemplate::query()->create(['code' => 'disabled-status', 'channel' => 'sms', 'purpose' => 'transactional', 'version' => 1, 'content' => '{{school_name}} status: {{status}}', 'is_active' => true, 'created_by' => $admin->id]);
        $notification = app(QueueSmsNotificationAction::class)->execute($template, $guardianProfile, 'disabled:'.$guardianProfile->id, ['school_name' => 'JCA', 'status' => 'updated', 'action_url' => '', 'announcement_title' => '', 'announcement_body' => '']);
        Http::fake();
        (new DispatchSmsNotification($notification->id))->handle(app(SmsGateway::class), app(AuditRecorder::class));
        $this->assertSame('disabled', $notification->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_admin_controls_templates_and_failed_delivery_retries_without_sensitive_content(): void
    {
        $admin = $this->user(['admin']);
        $accounting = $this->user(['accounting']);
        $guardian = $this->user(['guardian']);
        $this->actingAs($admin)
            ->get(route('communications.admin'))
            ->assertOk()
            ->assertSee('New announcement')
            ->assertSee('Create SMS template version')
            ->assertSee('Failed SMS deliveries');
        $this->actingAs($accounting)->post(route('communications.store'), ['title' => 'No access', 'body' => 'General school update.', 'status' => 'published', 'audiences' => [['type' => 'all']]])->assertForbidden();
        $draftResponse = $this->actingAs($admin)->post(route('communications.store'), ['title' => 'Draft school update', 'body' => 'General school update.', 'status' => 'draft', 'audiences' => [['type' => 'all']]]);
        $draft = Announcement::query()->where('title', 'Draft school update')->firstOrFail();
        $draftResponse->assertRedirect(route('communications.show', $draft));
        $this->actingAs($admin)->get(route('communications.show', $draft))->assertOk()->assertSee('Draft school update');
        $this->actingAs($guardian)->get(route('communications.index'))->assertOk()->assertDontSee('Draft school update');
        $this->actingAs($admin)->post(route('communications.store'), ['title' => '<script>Unsafe</script>', 'body' => 'General school update.', 'status' => 'published', 'audiences' => [['type' => 'role', 'role_code' => 'guardian']]])->assertRedirect();
        $this->actingAs($guardian)->get(route('communications.index'))->assertOk()->assertSee('&lt;script&gt;Unsafe&lt;/script&gt;', false);
        $this->actingAs($admin)->post(route('communications.templates.store'), ['code' => 'unsafe', 'purpose' => 'transactional', 'content' => 'Your payment balance is {{status}}', 'is_active' => true])->assertSessionHasErrors('content');
        $this->actingAs($admin)->post(route('communications.templates.store'), ['code' => 'saved-inactive', 'purpose' => 'transactional', 'content' => '{{school_name}}: enrollment status updated.', 'is_active' => false])->assertRedirect();
        $this->assertDatabaseHas('notification_templates', ['code' => 'saved-inactive', 'version' => 1, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'communications.template.created']);

        $recipient = GuardianProfile::query()->create(['user_id' => $guardian->id, 'first_name' => 'Fictional', 'last_name' => 'Recipient', 'mobile' => '09179999999']);
        SmsConsent::query()->create(['guardian_id' => $recipient->id, 'purpose' => 'announcement', 'status' => 'granted', 'source' => 'fictional-test', 'effective_at' => now(), 'recorded_by' => $guardian->id]);
        $announcementTemplate = NotificationTemplate::query()->create(['code' => 'announcement-safe', 'channel' => 'sms', 'purpose' => 'announcement', 'version' => 1, 'content' => '{{school_name}}: {{announcement_title}} {{action_url}}', 'is_active' => true, 'created_by' => $admin->id]);
        Queue::fake();
        $this->actingAs($admin)->post(route('communications.store'), ['title' => 'SMS school update', 'body' => 'General school update.', 'status' => 'published', 'sms_template_public_id' => $announcementTemplate->public_id, 'audiences' => [['type' => 'role', 'role_code' => 'guardian']]])->assertRedirect();
        $this->assertDatabaseHas('notifications', ['recipient_guardian_id' => $recipient->id, 'purpose' => 'announcement', 'status' => 'queued']);

        $notification = Notification::query()->create(['purpose' => 'transactional', 'status' => 'failed', 'idempotency_key' => 'failed-'.Str::random(10), 'scheduled_at' => now(), 'failed_at' => now()]);
        $this->actingAs($accounting)->post(route('communications.notifications.retry', $notification))->assertForbidden();
        $this->actingAs($admin)->post(route('communications.notifications.retry', $notification))->assertRedirect();
        $this->assertSame('queued', $notification->fresh()->status);
    }

    public function test_admin_communications_workspace_supports_ajax_json_responses(): void
    {
        $admin = $this->user(['admin']);
        $guardian = $this->user(['guardian']);
        $existing = $this->announcement($admin, 'Preview target notice', 'all');

        // Table section returned as JSON for AJAX pagination.
        $this->actingAs($admin)
            ->getJson(route('communications.admin'))
            ->assertOk()
            ->assertJson(['section' => 'announcements'])
            ->assertJsonStructure(['section', 'html'])
            ->assertSee('Preview target notice', false);

        // Creating an announcement over AJAX returns the refreshed table + toast.
        $this->actingAs($admin)
            ->postJson(route('communications.store'), [
                'title' => 'Ajax created notice',
                'body' => 'General school update.',
                'status' => 'published',
                'audiences' => [['type' => 'all']],
            ])
            ->assertOk()
            ->assertJson(['section' => 'announcements', 'message' => 'Announcement saved.'])
            ->assertSee('Ajax created notice', false);
        $this->assertDatabaseHas('announcements', ['title' => 'Ajax created notice']);

        // Template creation over AJAX returns the templates section.
        $this->actingAs($admin)
            ->postJson(route('communications.templates.store'), [
                'code' => 'ajax-template',
                'purpose' => 'announcement',
                'content' => '{{school_name}}: {{announcement_title}}',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJson(['section' => 'templates', 'message' => 'Template version saved.']);

        // Single-announcement preview payload for the modal.
        $this->actingAs($admin)
            ->getJson(route('communications.preview', $existing))
            ->assertOk()
            ->assertJson([
                'title' => 'Preview target notice',
                'status' => 'published',
                'audiences' => ['All portals'],
            ])
            ->assertJsonStructure(['title', 'body', 'status', 'statusLabel', 'publishedAt', 'author', 'audiences', 'url']);

        // The preview endpoint is admin-only.
        $this->actingAs($guardian)->getJson(route('communications.preview', $existing))->assertForbidden();
    }

    public function test_admin_can_publish_a_draft_announcement_with_optional_sms(): void
    {
        $admin = $this->user(['admin']);
        $guardianUser = $this->user(['guardian']);
        $recipient = GuardianProfile::query()->create(['user_id' => $guardianUser->id, 'first_name' => 'Draft', 'last_name' => 'Recipient', 'mobile' => '09171234567']);
        SmsConsent::query()->create(['guardian_id' => $recipient->id, 'purpose' => 'announcement', 'status' => 'granted', 'source' => 'fictional-test', 'effective_at' => now(), 'recorded_by' => $guardianUser->id]);
        $template = NotificationTemplate::query()->create(['code' => 'guardian-announcement', 'channel' => 'sms', 'purpose' => 'announcement', 'version' => 1, 'content' => '{{school_name}}: {{announcement_title}}', 'is_active' => true, 'created_by' => $admin->id]);

        $draft = Announcement::query()->create(['title' => 'Welcome to JCSGO', 'body' => 'General school update.', 'status' => 'draft', 'created_by' => $admin->id]);
        AnnouncementAudience::query()->create(['announcement_id' => $draft->id, 'audience_type' => 'role', 'role_code' => 'guardian']);

        // Non-admins cannot publish.
        $this->actingAs($guardianUser)->post(route('communications.publish', $draft))->assertForbidden();

        // Publish with SMS: the draft goes live and a message is queued to the consented guardian.
        Queue::fake();
        $this->actingAs($admin)
            ->post(route('communications.publish', $draft), ['sms_template_public_id' => $template->public_id])
            ->assertRedirect();

        $draft->refresh();
        $this->assertSame('published', $draft->status);
        $this->assertNotNull($draft->published_at);
        $this->assertDatabaseHas('notifications', ['recipient_guardian_id' => $recipient->id, 'purpose' => 'announcement', 'status' => 'queued']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'communications.announcement.published']);

        // Re-publishing an already-published announcement is rejected.
        $this->actingAs($admin)->post(route('communications.publish', $draft))->assertSessionHasErrors('status');
    }

    public function test_admin_can_manage_sms_template_versions(): void
    {
        $admin = $this->user(['admin']);
        $guardianUser = $this->user(['guardian']);

        $v1 = NotificationTemplate::query()->create(['code' => 'welcome-sms', 'channel' => 'sms', 'purpose' => 'announcement', 'version' => 1, 'content' => '{{school_name}} v1', 'is_active' => true, 'created_by' => $admin->id]);
        $v2 = NotificationTemplate::query()->create(['code' => 'welcome-sms', 'channel' => 'sms', 'purpose' => 'announcement', 'version' => 2, 'content' => '{{school_name}} v2', 'is_active' => false, 'created_by' => $admin->id]);

        // Only admins may manage versions.
        $this->actingAs($guardianUser)->patch(route('communications.templates.status', $v2), ['is_active' => 1])->assertForbidden();
        $this->actingAs($guardianUser)->delete(route('communications.templates.destroy', $v2))->assertForbidden();

        // Activating v2 deactivates its sibling v1 (one active per code).
        $this->actingAs($admin)->patch(route('communications.templates.status', $v2), ['is_active' => 1])->assertRedirect();
        $this->assertFalse($v1->fresh()->is_active);
        $this->assertTrue($v2->fresh()->is_active);

        // Deactivating a version turns it off without touching others.
        $this->actingAs($admin)->patch(route('communications.templates.status', $v2), ['is_active' => 0])->assertRedirect();
        $this->assertFalse($v2->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'communications.template.deactivated']);

        // An unused version can be deleted.
        $this->actingAs($admin)->delete(route('communications.templates.destroy', $v1))->assertRedirect();
        $this->assertDatabaseMissing('notification_templates', ['id' => $v1->id]);

        // A used version cannot be deleted — it must be deactivated instead.
        $recipient = GuardianProfile::query()->create(['user_id' => $guardianUser->id, 'first_name' => 'Used', 'last_name' => 'Recipient', 'mobile' => '09170000000']);
        Notification::query()->create(['notification_template_id' => $v2->id, 'recipient_guardian_id' => $recipient->id, 'purpose' => 'announcement', 'status' => 'accepted', 'idempotency_key' => 'used-'.Str::random(10), 'scheduled_at' => now()]);
        $this->actingAs($admin)->delete(route('communications.templates.destroy', $v2))->assertSessionHasErrors('template');
        $this->assertDatabaseHas('notification_templates', ['id' => $v2->id]);
    }

    /** @return array{0: StudentProfile, 1: Enrollment, 2: Section} */
    private function studentFixture(User $guardianUser, ?User $studentUser = null): array
    {
        $year = AcademicYear::query()->create(['code' => 'FY-'.Str::random(8), 'name' => 'Fictional 2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => 'active']);
        $grade = GradeLevel::query()->create(['code' => 'G7-'.Str::random(4), 'name' => 'Grade 7', 'sequence' => random_int(100, 999), 'is_active' => true]);
        $section = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Fictional Section '.Str::random(4), 'capacity' => 15, 'status' => 'active']);
        $guardian = GuardianProfile::query()->create(['user_id' => $guardianUser->id, 'first_name' => 'Fictional', 'last_name' => 'Guardian']);
        $student = StudentProfile::query()->create(['user_id' => $studentUser?->id, 'first_name' => 'Fictional', 'last_name' => 'Student']);
        GuardianStudent::query()->create(['guardian_id' => $guardian->id, 'student_id' => $student->id, 'relationship_type' => 'parent', 'may_manage_enrollment' => true, 'may_receive_sms' => true, 'verified_at' => now()]);
        $enrollment = Enrollment::query()->create(['application_number' => 'APP-'.Str::random(10), 'student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'applicant_guardian_id' => $guardian->id, 'student_category' => 'regular', 'status' => 'enrolled', 'active_enrollment_key' => $student->id.':'.$year->id]);
        SectionAssignment::query()->create(['enrollment_id' => $enrollment->id, 'section_id' => $section->id, 'status' => 'confirmed', 'active_assignment_key' => (string) $enrollment->id, 'confirmed_by' => $guardianUser->id, 'confirmed_at' => now(), 'effective_from' => today()]);

        return [$student, $enrollment, $section];
    }

    private function announcement(User $author, string $title, string $type, ?int $guardianId = null, ?int $sectionId = null, ?int $studentId = null): Announcement
    {
        $record = Announcement::query()->create(['title' => $title, 'body' => 'General school update.', 'status' => 'published', 'published_at' => now(), 'created_by' => $author->id]);
        AnnouncementAudience::query()->create(['announcement_id' => $record->id, 'audience_type' => $type, 'guardian_id' => $guardianId, 'section_id' => $sectionId, 'student_id' => $studentId]);

        return $record;
    }

    /** @param list<string> $roles */
    private function user(array $roles): User
    {
        $user = User::query()->create(['name' => 'Fictional '.Str::random(6), 'email' => Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
