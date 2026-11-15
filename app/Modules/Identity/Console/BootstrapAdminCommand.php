<?php

namespace App\Modules\Identity\Console;

use App\Models\User;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Actions\ContactNormalizer;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Role;
use App\Modules\People\Actions\IssueStaffNumberAction;
use App\Modules\People\Models\StaffProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BootstrapAdminCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'identity:bootstrap-admin
                            {--name= : Administrator full name}
                            {--email= : Administrator email address}
                            {--mobile= : Administrator mobile number}
                            {--employed-on= : Employment start date (YYYY-MM-DD)}';

    /**
     * @var string
     */
    protected $description = 'Create the first JCA administrator account through an interactive secure prompt';

    public function handle(AssignRolesAction $assignRoles, AuditRecorder $auditRecorder, IssueStaffNumberAction $issueStaffNumber): int
    {
        if (Role::query()->where('code', 'admin')->whereHas('users')->exists()) {
            $this->error('An active administrator role assignment already exists. Use the Admin user-management screen instead.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Administrator full name');
        $email = ContactNormalizer::email($this->option('email') ?: $this->ask('Administrator email address (optional)'));
        $mobile = ContactNormalizer::mobile($this->option('mobile') ?: $this->ask('Administrator mobile number (optional)'));
        $password = $this->secret('Temporary administrator password');
        $confirmation = $this->secret('Confirm temporary administrator password');
        $employedOn = $this->option('employed-on') ?: today()->toDateString();

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'mobile' => $mobile,
            'password' => $password,
            'password_confirmation' => $confirmation,
            'employed_on' => $employedOn,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'required_without:mobile'],
            'mobile' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'employed_on' => ['required', 'date', 'before_or_equal:today'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'mobile' => $mobile,
            'password' => $password,
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);
        $user = $assignRoles->execute($user, ['admin'], null);
        $staff = StaffProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['active_from' => $employedOn],
        );
        $issueStaffNumber->execute($staff, Carbon::parse($employedOn));
        $auditRecorder->record(null, 'identity.initial_admin.created', $user);

        $this->info('Administrator account created. Sign in through /login and change the password if it was shared during setup.');

        return self::SUCCESS;
    }
}
