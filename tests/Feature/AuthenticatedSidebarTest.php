<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticatedSidebarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_authenticated_desktop_layout_contains_a_permanent_scrollable_sidebar_and_mobile_drawer_controls(): void
    {
        $response = $this->actingAs($this->user(['registrar']))
            ->get(route('identity.dashboard'));

        $response->assertOk()
            ->assertSee('JCSGO Christian')
            ->assertSee('Academy R1-Inc.')
            ->assertSee(asset('images/logo/jca_logo.png'), false)
            ->assertSee('id="primary-navigation"', false)
            ->assertSee('x-show="navigationOpen"', false)
            ->assertSee('lg:!flex', false)
            ->assertSee('overflow-y-auto', false)
            ->assertSee('aria-controls="primary-navigation"', false)
            ->assertSee('@keydown.escape.window="navigationOpen = false"', false)
            ->assertSee('@click="navigationOpen = false"', false);
    }

    public function test_authenticated_shell_has_grouped_icon_navigation_and_an_active_page_state(): void
    {
        $response = $this->actingAs($this->user(['admin']))
            ->get(route('identity.dashboard'));

        $response->assertOk()
            ->assertSee('Overview')
            ->assertSee('School records')
            ->assertSee('Finance')
            ->assertSee('Administration')
            ->assertSee('aria-current="page"', false)
            ->assertSee('<svg', false)
            ->assertDontSee('Foundation');
    }

    public function test_registrar_sidebar_contains_only_registrar_authorized_navigation(): void
    {
        $response = $this->actingAs($this->user(['registrar']))
            ->get(route('identity.dashboard'));

        $response->assertOk()
            ->assertSee('Enrollment review')
            ->assertSee('Section assignment')
            ->assertSee('Academics')
            ->assertSee('Reports')
            ->assertDontSee('Financial configuration')
            ->assertDontSee('Communications')
            ->assertSee('Family links')
            ->assertDontSee('Users')
            ->assertDontSee('People foundation')
            ->assertDontSee('Audit');
    }

    public function test_admin_sidebar_contains_admin_navigation(): void
    {
        $response = $this->actingAs($this->user(['admin']))
            ->get(route('identity.dashboard'));

        $response->assertOk()
            ->assertSee('Enrollment review')
            ->assertSee('Financial configuration')
            ->assertSee('Communications')
            ->assertSee('Users')
            ->assertSee('Family links')
            ->assertSee('Audit');
    }

    public function test_teacher_sidebar_omits_restricted_staff_navigation(): void
    {
        $response = $this->actingAs($this->user(['teacher']))
            ->get(route('identity.dashboard'));

        $response->assertOk()
            ->assertSee('My teaching')
            ->assertSee('Reports')
            ->assertDontSee('Enrollment review')
            ->assertDontSee('Billing queue')
            ->assertDontSee('Financial configuration')
            ->assertDontSee('Restricted support review')
            ->assertDontSee('Communications')
            ->assertDontSee('Users')
            ->assertDontSee('People')
            ->assertDontSee('Audit');
    }

    public function test_admin_workspaces_use_finished_ui_language_and_styled_controls(): void
    {
        $admin = $this->user(['admin']);

        $this->actingAs($admin)
            ->get(route('section-assignment.index'))
            ->assertOk()
            ->assertSee('Registrar workspace')
            ->assertSee('Awaiting placement')
            ->assertDontSee('Phase 5 workspace');

        $this->actingAs($admin)
            ->get(route('billing.configuration.index'))
            ->assertOk()
            ->assertSee('Finance workspace')
            ->assertSee('Manage school-year fee rules')
            ->assertSee('jca-input', false)
            ->assertSee('School non-business days')
            ->assertDontSee('Development fixtures are not production policy');
    }

    /** @param list<string> $roles */
    private function user(array $roles): User
    {
        $user = User::query()->create([
            'name' => 'Sidebar '.Str::random(6),
            'email' => Str::lower(Str::random(12)).'@example.test',
            'password' => Hash::make('Password12345'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
