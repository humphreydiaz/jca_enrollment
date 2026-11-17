<?php

namespace App\Modules\Communications\Queries;

use App\Models\User;
use App\Modules\Communications\Models\AnnouncementAudience;
use App\Modules\Communications\Models\SmsConsent;
use App\Modules\Communications\Support\SmsRecipient;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * D-052: who can be reached by SMS.
 *
 * Announcement audiences are role groups: parents/guardians with an active link to a learner, learners
 * with a current (non-closed) enrollment, and active staff accounts per role. Announcement SMS goes only to
 * people with a number on file who opted in (Data Privacy Act); a number shared by several people gets one text.
 * Admin direct messages may pick anyone with a number on file, opted in or not.
 */
class SmsRecipientDirectory
{
    public const STAFF_ROLES = ['teacher', 'registrar', 'accounting', 'admin'];

    /**
     * @param  list<string>  $roles  role codes from AnnouncementAudience::ROLES
     * @return array{recipients: Collection<int, SmsRecipient>, groups: array<string, array{label: string, members: int, with_number: int, opted_in: int}>}
     */
    public function forAudience(array $roles): array
    {
        $groups = [];
        $recipients = collect();
        foreach (array_keys(AnnouncementAudience::ROLES) as $role) {
            if (! in_array($role, $roles, true)) {
                continue;
            }
            $members = $this->members($role);
            $withNumber = (clone $members)->whereNotNull('mobile')->where('mobile', '!=', '');
            $optedIn = $this->optedIn(clone $withNumber, $role);
            $groups[$role] = [
                'label' => AnnouncementAudience::ROLES[$role],
                'members' => (clone $members)->count(),
                'with_number' => (clone $withNumber)->count(),
                'opted_in' => (clone $optedIn)->count(),
            ];
            $recipients = $recipients->merge($this->toRecipients($role, $optedIn->get()));
        }

        return ['recipients' => $recipients->unique(fn (SmsRecipient $recipient) => $recipient->mobileHash())->values(), 'groups' => $groups];
    }

    /**
     * People with a number on file, for the Admin's "Message people" search.
     *
     * @return Collection<int, SmsRecipient>
     */
    public function search(string $term, ?string $group = null, int $limit = 25): Collection
    {
        $term = trim($term);
        $like = '%'.$term.'%';
        $digits = preg_replace('/\D/', '', $term) ?: null;
        $results = collect();

        foreach (array_keys(AnnouncementAudience::ROLES) as $role) {
            if ($group !== null && $group !== '' && $group !== $role) {
                continue;
            }
            $query = $this->members($role)->whereNotNull('mobile')->where('mobile', '!=', '');
            if ($term !== '') {
                $query->where(function (Builder $match) use ($role, $like, $digits): void {
                    match ($role) {
                        'guardian' => $match->where(fn (Builder $q) => $this->nameLike($q, $like))
                            ->orWhereHas('students', fn (Builder $child) => $this->nameLike($child, $like)->orWhere('student_number', 'like', $like)),
                        'student' => $match->where(fn (Builder $q) => $this->nameLike($q, $like))->orWhere('student_number', 'like', $like),
                        default => $match->where('name', 'like', $like)->orWhereHas('staffProfile', fn (Builder $staff) => $staff->where('employee_number', 'like', $like)),
                    };
                    if ($digits !== null && strlen($digits) >= 4) {
                        $match->orWhere('mobile', 'like', '%'.$digits.'%');
                    }
                });
            }
            $orderColumn = in_array($role, self::STAFF_ROLES, true) ? 'name' : 'last_name';
            $results = $results->merge($this->toRecipients($role, $query->orderBy($orderColumn)->limit($limit)->get()));
        }

        return $results->unique(fn (SmsRecipient $recipient) => $recipient->key())->take($limit)->values();
    }

    /**
     * Turn picked keys ("guardian:01J…", "student:01J…", "user:01J…") back into people with a number.
     *
     * @param  list<string>  $keys
     * @return Collection<int, SmsRecipient>
     */
    public function resolve(array $keys): Collection
    {
        $byType = collect($keys)->map(fn (string $key) => explode(':', $key, 2))->filter(fn (array $parts) => count($parts) === 2)->groupBy(0)->map(fn (Collection $rows) => $rows->pluck(1)->all());
        $recipients = collect();
        if ($byType->has('guardian')) {
            $recipients = $recipients->merge($this->toRecipients('guardian', GuardianProfile::query()->with('students')->whereIn('public_id', $byType['guardian'])->whereNotNull('mobile')->where('mobile', '!=', '')->get()));
        }
        if ($byType->has('student')) {
            $recipients = $recipients->merge($this->toRecipients('student', StudentProfile::query()->with('latestEnrollment.gradeLevel')->whereIn('public_id', $byType['student'])->whereNotNull('mobile')->where('mobile', '!=', '')->get()));
        }
        if ($byType->has('user')) {
            $recipients = $recipients->merge($this->toRecipients('teacher', User::query()->with(['roles', 'staffProfile'])->whereHas('roles', fn (Builder $roles) => $roles->whereIn('code', self::STAFF_ROLES))->whereIn('public_id', $byType['user'])->whereNotNull('mobile')->where('mobile', '!=', '')->get()));
        }

        return $recipients->unique(fn (SmsRecipient $recipient) => $recipient->key())->values();
    }

    /** @return Builder<GuardianProfile>|Builder<StudentProfile>|Builder<User> */
    private function members(string $role): Builder
    {
        return match ($role) {
            'guardian' => GuardianProfile::query()->with('students')->whereHas('studentLinks', fn (Builder $link) => $link->whereNull('ended_at')),
            'student' => StudentProfile::query()->with('latestEnrollment.gradeLevel')->whereHas('enrollments', fn (Builder $enrollment) => $enrollment->whereNotIn('status', Enrollment::TERMINAL_STATUSES)),
            default => User::query()->with(['roles', 'staffProfile'])->where('status', 'active')->whereHas('roles', fn (Builder $roles) => $roles->where('code', $role)),
        };
    }

    private function optedIn(Builder $query, string $role): Builder
    {
        $column = match ($role) {
            'guardian' => 'guardian_id',
            'student' => 'student_id',
            default => 'user_id',
        };

        return $query->whereIn($query->getModel()->getQualifiedKeyName(), SmsConsent::query()
            ->select($column)->whereNotNull($column)->where('purpose', 'announcement')->where('status', 'granted')->whereNull('withdrawn_at'));
    }

    private function nameLike(Builder $query, string $like): Builder
    {
        return $query->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
            ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", [$like]);
    }

    /**
     * @param  Collection<int, GuardianProfile|StudentProfile|User>  $people
     * @return Collection<int, SmsRecipient>
     */
    private function toRecipients(string $role, Collection $people): Collection
    {
        return $people->map(function ($person): SmsRecipient {
            if ($person instanceof GuardianProfile) {
                $children = $person->students;

                return new SmsRecipient('guardian', $person->id, $person->user_id, $person->full_name, (string) $person->mobile, $person->public_id,
                    $children->isEmpty() ? 'Parent/Guardian' : 'Parent of '.$children->pluck('full_name')->join(', ', ' and '));
            }
            if ($person instanceof StudentProfile) {
                $enrollment = $person->latestEnrollment;

                return new SmsRecipient('student', $person->id, $person->user_id, $person->full_name, (string) $person->mobile, $person->public_id,
                    collect(['Student', $enrollment?->gradeLevel?->name, $person->student_number])->filter()->join(' · '));
            }
            $roles = $person->roles->pluck('name')->join(', ');

            return new SmsRecipient('user', $person->id, $person->id, (string) $person->name, (string) $person->mobile, $person->public_id,
                collect([$roles ?: 'Staff', $person->staffProfile?->employee_number])->filter()->join(' · '));
        });
    }
}
