<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Communications\Actions\SendAnnouncementSmsAction;
use App\Modules\Communications\Actions\UpdateSmsConsentAction;
use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Enums\SmsDeliveryRoute;
use App\Modules\Communications\Gateways\FakeSmsGateway;
use App\Modules\Communications\Gateways\SemaphoreSmsGateway;
use App\Modules\Communications\Jobs\DispatchSmsNotification;
use App\Modules\Communications\Models\Announcement;
use App\Modules\Communications\Models\AnnouncementAudience;
use App\Modules\Communications\Models\DirectMessage;
use App\Modules\Communications\Models\Notification;
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

    public function test_announcement_sms_reaches_only_opted_in_people_once_on_the_priority_route(): void
    {
        $admin = $this->user(['admin']);
        $guardianUser = $this->user(['guardian']);
        $this->studentFixture($guardianUser);
        $guardian = $guardianUser->guardianProfile;
        $guardian->update(['mobile' => '+639171234567']);
        Queue::fake();

        // Not opted in: the portal post goes out, no text is queued.
        $this->actingAs($admin)->post(route('communications.store'), ['title' => 'Field trip', 'body' => 'Field trip on Friday.', 'delivery' => 'portal_sms', 'sms_body' => 'JCSGO: Field trip on Friday.', 'status' => 'published', 'audience_roles' => ['guardian']])->assertRedirect();
        $this->assertDatabaseCount('notifications', 0);

        SmsConsent::query()->create(['guardian_id' => $guardian->id, 'purpose' => 'announcement', 'status' => 'granted', 'source' => 'fictional-test', 'effective_at' => now(), 'recorded_by' => $guardianUser->id]);
        $this->actingAs($admin)->post(route('communications.store'), ['title' => 'No classes', 'body' => 'No classes on Monday.', 'delivery' => 'portal_sms', 'sms_body' => 'JCSGO: No classes on Monday.', 'status' => 'published', 'audience_roles' => ['guardian']])->assertRedirect()
            ->assertSessionHas('status', fn (string $message) => str_contains($message, 'text queued to 1 person'));
        $announcement = Announcement::query()->where('title', 'No classes')->sole();
        $notification = Notification::query()->sole();
        $this->assertSame(['announcement', $guardian->id, 'queued'], [$notification->purpose, $notification->recipient_guardian_id, $notification->status]);

        // Sending the same announcement again never texts the same number twice.
        app(SendAnnouncementSmsAction::class)->execute($announcement, $admin);
        $this->assertDatabaseCount('notifications', 1);

        $fake = new FakeSmsGateway;
        (new DispatchSmsNotification($notification->id))->handle($fake, app(AuditRecorder::class));
        (new DispatchSmsNotification($notification->id))->handle($fake, app(AuditRecorder::class));
        $this->assertCount(1, $fake->sent);
        $this->assertSame(SmsDeliveryRoute::Priority, $fake->sent[0]['route']);
        $this->assertSame('JCSGO: No classes on Monday.', $fake->sent[0]['body']);
        $this->assertSame('accepted', $notification->fresh()->status);
        $this->assertDatabaseHas('sms_messages', ['notification_id' => $notification->id, 'attempt_count' => 1, 'status' => 'accepted', 'recipient_last_four' => '4567']);

        // A failed text can be retried for the whole announcement.
        $notification->update(['status' => 'failed', 'failed_at' => now()]);
        $this->actingAs($admin)->post(route('communications.retry-failed', ['announcement', $announcement->public_id]))->assertRedirect();
        $this->assertSame('queued', $notification->fresh()->status);

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

        $teacher->update(['mobile' => '+639170000000']);
        Queue::fake();
        $this->actingAs($admin)->post(route('communications.direct'), ['recipients' => ['user:'.$teacher->public_id], 'body' => 'Please see the Admin office.'])->assertRedirect();
        $notification = Notification::query()->sole();
        Http::fake();
        (new DispatchSmsNotification($notification->id))->handle(app(SmsGateway::class), app(AuditRecorder::class));
        $this->assertSame('disabled', $notification->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_admin_page_access_drafts_sms_only_posts_and_content_guard(): void
    {
        $admin = $this->user(['admin']);
        $accounting = $this->user(['accounting']);
        $guardian = $this->user(['guardian']);
        $this->actingAs($admin)->get(route('communications.admin'))->assertOk()
            ->assertSee('New announcement')->assertSee('Message people')->assertSee('Sent history')
            ->assertSee('Parents/Guardians')->assertSee('Accounting')->assertSee('Portal + SMS')
            ->assertDontSee('SMS template')->assertDontSee('Template code');
        foreach (['registrar', 'accounting', 'teacher', 'guardian', 'student'] as $role) {
            $this->actingAs($this->user([$role]))->get(route('communications.admin'))->assertForbidden();
        }
        $this->actingAs($accounting)->post(route('communications.store'), ['title' => 'No access', 'body' => 'General school update.', 'delivery' => 'portal', 'status' => 'published', 'everyone' => '1'])->assertForbidden();

        $this->actingAs($admin)->post(route('communications.store'), ['title' => 'Draft school update', 'body' => 'General school update.', 'delivery' => 'portal', 'status' => 'draft', 'everyone' => '1'])->assertRedirect(route('communications.admin'));
        $draft = Announcement::query()->where('title', 'Draft school update')->firstOrFail();
        $this->assertSame('all', $draft->audiences()->sole()->audience_type);
        $this->actingAs($guardian)->get(route('communications.index'))->assertOk()->assertDontSee('Draft school update');

        $this->actingAs($admin)->post(route('communications.store'), ['title' => '<script>Unsafe</script>', 'body' => 'General school update.', 'delivery' => 'portal', 'status' => 'published', 'audience_roles' => ['guardian']])->assertRedirect();
        $this->actingAs($guardian)->get(route('communications.index'))->assertOk()->assertSee('&lt;script&gt;Unsafe&lt;/script&gt;', false);

        // SMS only: a text, not a portal post.
        Queue::fake();
        $this->actingAs($admin)->post(route('communications.store'), ['title' => 'Typhoon text', 'delivery' => 'sms', 'sms_body' => 'JCSGO: Classes suspended today.', 'status' => 'published', 'audience_roles' => ['guardian']])->assertRedirect();
        $this->assertSame('JCSGO: Classes suspended today.', Announcement::query()->where('title', 'Typhoon text')->value('body'));
        $this->actingAs($guardian)->get(route('communications.index'))->assertDontSee('Typhoon text');
        $this->actingAs($guardian)->get(route('identity.dashboard'))->assertDontSee('Typhoon text');

        // Validation: audience, SMS text, length, and confidential words in announcements.
        $this->actingAs($admin)->post(route('communications.store'), ['title' => 'No audience', 'body' => 'Hello.', 'delivery' => 'portal', 'status' => 'published'])->assertSessionHasErrors('audience_roles');
        $this->actingAs($admin)->post(route('communications.store'), ['title' => 'No text', 'body' => 'Hello.', 'delivery' => 'portal_sms', 'status' => 'published', 'everyone' => '1'])->assertSessionHasErrors('sms_body');
        $this->actingAs($admin)->post(route('communications.store'), ['title' => 'Too long', 'delivery' => 'sms', 'sms_body' => str_repeat('a', 470), 'status' => 'published', 'everyone' => '1'])->assertSessionHasErrors('sms_body');
        $this->actingAs($admin)->post(route('communications.store'), ['title' => 'Fees', 'body' => 'Hello.', 'delivery' => 'portal_sms', 'sms_body' => 'Please settle your payment.', 'status' => 'published', 'everyone' => '1'])->assertSessionHasErrors('sms_body');
        $this->actingAs($admin)->post(route('communications.store'), ['title' => 'Bad role', 'body' => 'Hello.', 'delivery' => 'portal', 'status' => 'published', 'audience_roles' => ['parent']])->assertSessionHasErrors('audience_roles.0');

        $notification = Notification::query()->create(['purpose' => 'announcement', 'status' => 'failed', 'idempotency_key' => 'failed-'.Str::random(10), 'scheduled_at' => now(), 'failed_at' => now()]);
        $this->actingAs($accounting)->post(route('communications.notifications.retry', $notification))->assertForbidden();
        $this->actingAs($admin)->post(route('communications.notifications.retry', $notification))->assertRedirect();
        $this->assertSame('queued', $notification->fresh()->status);
    }

    public function test_admin_communications_workspace_supports_ajax_json_responses(): void
    {
        $admin = $this->user(['admin']);
        $guardian = $this->user(['guardian']);
        $existing = $this->announcement($admin, 'Preview target notice', 'all');

        $this->actingAs($admin)->getJson(route('communications.admin'))->assertOk()
            ->assertJson(['section' => 'history'])->assertJsonStructure(['section', 'html'])->assertSee('Preview target notice', false);

        $this->actingAs($admin)->postJson(route('communications.store'), [
            'title' => 'Ajax created notice', 'body' => 'General school update.', 'delivery' => 'portal', 'status' => 'published', 'everyone' => '1',
        ])->assertOk()->assertJson(['section' => 'history', 'message' => 'Announcement published on the portal.'])->assertSee('Ajax created notice', false);

        $this->actingAs($admin)->getJson(route('communications.preview', $existing))->assertOk()
            ->assertJson(['title' => 'Preview target notice', 'status' => 'published', 'audiences' => ['Everyone'], 'delivery' => 'Portal only'])
            ->assertJsonStructure(['title', 'body', 'smsBody', 'status', 'statusLabel', 'publishedAt', 'author', 'audiences', 'url']);
        $this->actingAs($guardian)->getJson(route('communications.preview', $existing))->assertForbidden();
    }

    public function test_admin_publishes_a_draft_and_its_text_goes_to_opted_in_people(): void
    {
        $admin = $this->user(['admin']);
        $guardianUser = $this->user(['guardian']);
        $this->studentFixture($guardianUser);
        $recipient = $guardianUser->guardianProfile;
        $recipient->update(['mobile' => '+639171234567']);
        SmsConsent::query()->create(['guardian_id' => $recipient->id, 'purpose' => 'announcement', 'status' => 'granted', 'source' => 'fictional-test', 'effective_at' => now(), 'recorded_by' => $guardianUser->id]);

        $draft = Announcement::query()->create(['title' => 'Welcome to JCSGO', 'body' => 'General school update.', 'delivery' => 'portal_sms', 'sms_body' => 'JCSGO: Welcome back!', 'status' => 'draft', 'created_by' => $admin->id]);
        AnnouncementAudience::query()->create(['announcement_id' => $draft->id, 'audience_type' => 'role', 'role_code' => 'guardian']);

        $this->actingAs($admin)->getJson(route('communications.draft-audience', $draft))->assertOk()->assertJson(['total' => 1, 'sends_sms' => true, 'parts' => 1, 'credits' => 2]);
        $this->actingAs($guardianUser)->post(route('communications.publish', $draft))->assertForbidden();

        Queue::fake();
        $this->actingAs($admin)->post(route('communications.publish', $draft))->assertRedirect();
        $draft->refresh();
        $this->assertSame('published', $draft->status);
        $this->assertNotNull($draft->published_at);
        $this->assertDatabaseHas('notifications', ['recipient_guardian_id' => $recipient->id, 'purpose' => 'announcement', 'status' => 'queued']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'communications.announcement.published']);
        $this->actingAs($admin)->post(route('communications.publish', $draft))->assertSessionHasErrors('status');
    }

    public function test_audience_counts_cover_every_group_and_staff_opt_in_from_preferences(): void
    {
        $admin = $this->user(['admin']);
        $guardianUser = $this->user(['guardian']);
        $studentUser = $this->user(['student']);
        [$student] = $this->studentFixture($guardianUser, $studentUser);
        $guardianUser->guardianProfile->update(['mobile' => '+639170000001']);
        $student->update(['mobile' => '+639170000002']);
        $teacher = $this->user(['teacher']);
        $teacher->update(['mobile' => '+639170000003']);
        $otherTeacher = $this->user(['teacher']);
        $this->user(['accounting']);

        // A teacher opts in from SMS preferences (staff numbers live on the account).
        $this->actingAs($teacher)->get(route('communications.preferences.edit'))->assertOk()->assertSee('As school staff')->assertSee('0917•••0003');
        $this->actingAs($teacher)->put(route('communications.preferences.update'), ['announcement' => ['staff' => '1']])->assertRedirect();
        $this->assertTrue(UpdateSmsConsentAction::granted($teacher));
        $this->actingAs($otherTeacher)->get(route('communications.preferences.edit'))->assertOk()->assertSee('No mobile number is on file');
        $this->actingAs($studentUser)->put(route('communications.preferences.update'), ['announcement' => ['student' => '1']])->assertRedirect();
        $this->get('/guardian/communication-preferences')->assertRedirect('/communication-preferences');

        $summary = $this->actingAs($admin)->postJson(route('communications.audience'), ['everyone' => '1', 'sms_body' => 'JCSGO: Foundation Day on Friday.'])->assertOk()->json();
        $groups = collect($summary['groups'])->keyBy('label');
        $this->assertSame(['members' => 1, 'with_number' => 1, 'opted_in' => 0], collect($groups['Parents/Guardians'])->only(['members', 'with_number', 'opted_in'])->all());
        $this->assertSame(['members' => 1, 'with_number' => 1, 'opted_in' => 1], collect($groups['Students'])->only(['members', 'with_number', 'opted_in'])->all());
        $this->assertSame(['members' => 2, 'with_number' => 1, 'opted_in' => 1], collect($groups['Teachers'])->only(['members', 'with_number', 'opted_in'])->all());
        $this->assertSame(1, $groups['Accounting']['members']);
        $this->assertSame(2, $summary['total']);
        $this->assertSame(4, $summary['credits']);

        Queue::fake();
        $this->actingAs($admin)->post(route('communications.store'), ['title' => 'Faculty meeting', 'delivery' => 'sms', 'sms_body' => 'JCSGO: Faculty meeting at 3 PM.', 'status' => 'published', 'audience_roles' => ['teacher']])->assertRedirect();
        $this->assertDatabaseHas('notifications', ['recipient_user_id' => $teacher->id, 'purpose' => 'announcement']);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_admin_texts_picked_people_whether_or_not_they_opted_in(): void
    {
        $admin = $this->user(['admin']);
        $guardianUser = $this->user(['guardian']);
        [$student] = $this->studentFixture($guardianUser);
        $guardian = $guardianUser->guardianProfile;
        $guardian->update(['first_name' => 'Rosa', 'last_name' => 'Cruz', 'mobile' => '+639171112222']);
        $student->update(['first_name' => 'Ana', 'last_name' => 'Cruz', 'student_number' => 'JCA-26-111', 'mobile' => '+639173334444']);
        $teacher = $this->user(['teacher']);
        $teacher->update(['name' => 'Teacher Lim', 'mobile' => '+639175556666']);
        $noNumber = $this->user(['teacher']);
        $noNumber->update(['name' => 'Teacher Without Number']);

        $people = $this->actingAs($admin)->getJson(route('communications.people', ['search' => 'Cruz']))->assertOk()->json('people');
        $this->assertEqualsCanonicalizing(['guardian:'.$guardian->public_id, 'student:'.$student->public_id], array_column($people, 'key'));
        $this->assertContains('0917•••2222', array_column($people, 'mobile'));
        $this->assertSame('Parent of Ana Cruz', collect($people)->firstWhere('key', 'guardian:'.$guardian->public_id)['detail']);
        $this->actingAs($admin)->getJson(route('communications.people', ['search' => 'JCA-26-111']))->assertJsonCount(2, 'people');
        $this->actingAs($admin)->getJson(route('communications.people', ['search' => 'Teacher', 'group' => 'teacher']))->assertJsonCount(1, 'people')->assertJsonPath('people.0.name', 'Teacher Lim');
        $this->actingAs($guardianUser)->getJson(route('communications.people', ['search' => 'Cruz']))->assertForbidden();

        Queue::fake();
        $this->actingAs($admin)->post(route('communications.direct'), [
            'recipients' => ['guardian:'.$guardian->public_id, 'student:'.$student->public_id, 'user:'.$teacher->public_id, 'user:'.$noNumber->public_id],
            'body' => 'Please pass by the Registrar tomorrow.',
        ])->assertRedirect()->assertSessionHas('status', 'Text message queued to 3 people.');
        $message = DirectMessage::query()->sole();
        $this->assertSame(3, $message->recipient_count);
        $this->assertSame(3, Notification::query()->where('purpose', 'direct')->where('subject_id', $message->id)->count());
        $this->assertDatabaseHas('notifications', ['recipient_student_id' => $student->id, 'purpose' => 'direct']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'communications.direct_message.sent', 'metadata' => json_encode(['body' => 'Please pass by the Registrar tomorrow.'])]);

        $fake = new FakeSmsGateway;
        Notification::query()->where('purpose', 'direct')->pluck('id')->each(fn ($id) => (new DispatchSmsNotification($id))->handle($fake, app(AuditRecorder::class)));
        $this->assertEqualsCanonicalizing(['+639171112222', '+639173334444', '+639175556666'], array_column($fake->sent, 'recipient'));

        $this->actingAs($admin)->get(route('communications.admin', ['tab' => 'messages']))->assertOk()->assertSee('Direct messages')->assertSee('Rosa Cruz');
        $this->actingAs($admin)->post(route('communications.direct'), ['recipients' => ['user:'.$noNumber->public_id], 'body' => 'Hi'])->assertSessionHasErrors('recipients');
        $this->actingAs($this->user(['registrar']))->post(route('communications.direct'), ['recipients' => ['user:'.$teacher->public_id], 'body' => 'Hi'])->assertForbidden();
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
