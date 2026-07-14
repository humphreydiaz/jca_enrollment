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
            ->assertSee('id="primary-navigation"', false)
            ->assertSee('x-show="navigationOpen"', false)
            ->assertSee('lg:!block', false)
            ->assertSee('overflow-y-auto', false)
            ->assertSee('aria-controls="primary-navigation"', false)
            ->assertSee('@keydown.escape.window="navigationOpen = false"', false)
            ->assertSee('@click="navigationOpen = false"', false);
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
            ->assertDontSee('Users')
            ->assertDontSee('People')
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
            ->assertSee('People')
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
