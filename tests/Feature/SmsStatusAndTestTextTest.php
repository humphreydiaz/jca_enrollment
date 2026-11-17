<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Communications\Actions\SendTestSmsAction;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\Shared\Audit\Models\AuditLog;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** D-070: SMS status panel, test text, and SMS preferences for every role. No real SMS is ever sent. */
class SmsStatusAndTestTextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    public function test_admin_sees_sms_is_off_and_how_to_turn_it_on_and_a_test_sends_nothing(): void
    {
        Http::fake();
        config(['communications.sms.enabled' => false, 'communications.sms.driver' => 'disabled', 'communications.sms.semaphore.api_key' => null]);
        $admin = $this->user(['admin']);

        $this->actingAs($admin)->get(route('communications.admin'))->assertOk()
            ->assertSee('SMS status and test')->assertSee('How to turn on live SMS')->assertSee('Not set');

        $this->actingAs($admin)->post(route('communications.sms-test'), ['test_mobile' => '09171234567'])
            ->assertSessionHasErrors('test_mobile');
        $this->assertStringContainsString('Live SMS is off', session('errors')->first('test_mobile'));
        Http::assertNothingSent();
    }

    public function test_live_mode_shows_balance_never_the_key_and_sends_a_test_without_a_sender_until_one_is_approved(): void
    {
        config(['communications.sms.enabled' => true, 'communications.sms.driver' => 'semaphore', 'communications.sms.message_route' => 'priority',
            'communications.sms.semaphore.api_key' => 'SECRET-KEY-DO-NOT-SHOW', 'communications.sms.semaphore.sender_name' => '']);
        Http::fake([
            '*/api/v4/account*' => Http::response(['account_name' => 'JCA', 'credit_balance' => 1234.5]),
            '*/api/v4/priority' => Http::response([['message_id' => 991, 'status' => 'Queued']]),
        ]);
        $admin = $this->user(['admin']);

        $this->actingAs($admin)->get(route('communications.admin'))->assertOk()
            ->assertSee('Live')->assertSee('Set (hidden)')->assertSee('1,234.50')->assertSee('Semaphore default')
            ->assertDontSee('SECRET-KEY-DO-NOT-SHOW')->assertDontSee('How to turn on live SMS');

        $this->actingAs($admin)->post(route('communications.sms-test'), ['test_mobile' => '0917 123 4567'])->assertSessionHasErrors('test_mobile');
        $this->actingAs($admin)->post(route('communications.sms-test'), ['test_mobile' => '09171234567'])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn (string $message) => str_contains($message, 'accepted by Semaphore'));

        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/api/v4/priority')
            && $request['number'] === '639171234567'
            && $request['message'] === SendTestSmsAction::MESSAGE
            && ! isset($request['sendername']));
        $audit = AuditLog::query()->where('action', 'communications.sms.test_sent')->sole();
        $this->assertSame('0917•••4567', $audit->metadata['recipient']);
        $this->assertSame('accepted', $audit->metadata['status']);
        $this->assertStringNotContainsString('1234567', json_encode($audit->metadata));
    }

    public function test_an_approved_sender_name_is_used_and_a_provider_refusal_is_explained(): void
    {
        config(['communications.sms.enabled' => true, 'communications.sms.driver' => 'semaphore', 'communications.sms.message_route' => 'standard',
            'communications.sms.semaphore.api_key' => 'fictional-key', 'communications.sms.semaphore.sender_name' => 'JCSGO']);
        Http::fake([
            '*/api/v4/account*' => Http::response([], 500),
            '*/api/v4/messages' => Http::sequence()->push([['message_id' => 5, 'status' => 'Pending']])->push(['error' => 'no credits'], 400),
        ]);
        $admin = $this->user(['admin']);

        $this->actingAs($admin)->get(route('communications.admin'))->assertOk()->assertSee('JCSGO')->assertSee('Could not be read');
        $this->actingAs($admin)->post(route('communications.sms-test'), ['test_mobile' => '+639181112222'])->assertSessionHasNoErrors();
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/api/v4/messages') && $request['sendername'] === 'JCSGO');

        $this->actingAs($admin)->post(route('communications.sms-test'), ['test_mobile' => '09181112222'])->assertSessionHasErrors('test_mobile');
        $this->assertStringContainsString('semaphore_http_400', session('errors')->first('test_mobile'));
    }

    public function test_a_stalled_queue_is_flagged_and_only_the_admin_can_send_a_test(): void
    {
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subHour()->getTimestamp(), 'created_at' => now()->subHour()->getTimestamp()]);
        $this->actingAs($this->user(['admin']))->get(route('communications.admin'))->assertOk()
            ->assertSee('the queue cron job is not running');

        auth()->logout();
        $this->post(route('communications.sms-test'), ['test_mobile' => '09171234567'])->assertRedirect(route('identity.login'));
        foreach (['registrar', 'accounting', 'teacher', 'guardian'] as $role) {
            $this->actingAs($this->user([$role]))->post(route('communications.sms-test'), ['test_mobile' => '09171234567'])->assertForbidden();
        }
    }

    public function test_every_role_can_open_sms_preferences_and_is_told_where_to_add_a_missing_number(): void
    {
        $guardian = $this->user(['guardian']);
        GuardianProfile::query()->create(['user_id' => $guardian->id, 'first_name' => 'No', 'last_name' => 'Number']);
        $this->actingAs($guardian)->get(route('communications.preferences.edit'))->assertOk()
            ->assertSee('Student Information')->assertSee(route('my-information.index'), false);

        $teacher = $this->user(['teacher']);
        $this->actingAs($teacher)->get(route('communications.preferences.edit'))->assertOk()->assertSee('Ask the school Admin');

        $accounting = $this->user(['accounting']);
        $accounting->update(['mobile' => '+639170001111']);
        $this->actingAs($accounting)->get(route('communications.preferences.edit'))->assertOk()->assertSee('0917•••1111');
        $this->actingAs($accounting)->put(route('communications.preferences.update'), ['announcement' => ['staff' => '1']])->assertSessionHas('status', 'SMS preferences saved.');
        $this->assertDatabaseHas('sms_consents', ['user_id' => $accounting->id, 'purpose' => 'announcement', 'status' => 'granted']);
    }

    /** @param list<string> $roles */
    private function user(array $roles): User
    {
        $user = User::query()->create(['name' => 'Fictional '.Str::random(6), 'email' => Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
