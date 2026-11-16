<?php

namespace App\Modules\Shared\Navigation;

use App\Models\User;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

/**
 * Builds the Explorer-style location bar: Dashboard > Module > ... > Current page.
 *
 * The hierarchy is declared here by route name (not guessed from the URL), so
 * labels are friendly and detail pages sit under the list they belong to.
 * Module labels come from the sidebar so both always use the same words.
 */
class BreadcrumbTrail
{
    /**
     * Logical parent of a page. A string is the parent for everyone; an array
     * maps role => parent route ('*' = anyone else, null = no parent).
     *
     * @var array<string, string|array<string, string|null>>
     */
    public const PARENTS = [
        // Students & enrollment
        'people.registrar.students.show' => 'people.registrar.students.index',
        'people.family.issue-both.create' => 'people.admin.index',
        'enrollment.registrar.issue-access.create' => 'enrollment.registrar.index',
        'enrollment.public.status' => [
            'admin' => 'enrollment.registrar.index',
            'registrar' => 'enrollment.registrar.index',
            'guardian' => 'guardian.enrollment.progress',
            'student' => 'student.enrollment.progress',
            '*' => null,
        ],
        'enrollment.public.start' => [
            'guardian' => 'guardian.enrollment.progress',
            'student' => 'student.enrollment.progress',
            '*' => null,
        ],

        // Academics & grading
        'academics.admin.setup' => 'academics.admin.index',
        'academics.admin.curricula.index' => 'academics.admin.index',
        'grading.admin.class' => 'grading.admin.index',
        'grading.teacher.show' => 'grading.teacher.index',
        'billing.teacher.status' => 'academics.teacher.assignments',
        'teacher.students.support-summary' => 'academics.teacher.assignments',
        'support.show' => 'support.queue',

        // Finance
        'billing.portal.show' => 'billing.portal.index',

        // Communications, reports, administration
        'communications.show' => 'communications.index',
        'reporting.show' => 'reporting.index',
        'reporting.audit.show' => 'reporting.audit.index',
        'operations.backups.restore.confirm' => 'operations.backups.index',
        'identity.users.reissue.index' => ['admin' => 'identity.admin.users.index', '*' => null],
    ];

    /**
     * Friendly labels for pages whose own title is missing, generic, or
     * formatted for the browser tab rather than for a location bar.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'identity.dashboard' => 'Dashboard',
        'academics.admin.index' => 'Academics',
        'academics.admin.setup' => 'Setup',
        'academics.admin.curricula.index' => 'Curriculum',
        'academics.teacher.assignments' => 'My classes',
        'academics.my-schedule' => 'My schedule',
        'academics.schedule-conflicts' => 'Schedule conflicts',
        'grading.admin.class' => 'Class grades',
        'grading.teacher.show' => 'Subject grades',
        'support.queue' => 'Restricted support',
        'support.show' => 'Support case',
        'teacher.students.support-summary' => 'Student summary',
        'billing.teacher.status' => 'Exam authorization',
        'billing.portal.show' => 'Financial summary',
        'billing.family.index' => 'Financial summary',
        'reporting.analytics' => 'School insights',
        'reporting.audit.index' => 'Audit trail',
        'reporting.information-updates.index' => 'Information updates',
        'reporting.audit.show' => 'Audit entry',
        'operations.backups.restore.confirm' => 'Restore backup',
        'identity.users.reissue.index' => 'Reissue temporary password',
        'people.family.issue-both.create' => 'Issue student & parent accounts',
        'enrollment.registrar.issue-access.create' => 'Issue walk-in access',
        'enrollment.public.status' => 'Enrollment application',
        'enrollment.public.start' => 'Enrollment application',
        'guardian.enrollment.progress' => 'Enrollment progress',
        'student.enrollment.progress' => 'My enrollment',
        'my-information.index' => 'Student information',
    ];

    /**
     * Pages whose own descriptive title (a learner's name, an announcement
     * title, a report name) is better than the generic label above.
     *
     * @var list<string>
     */
    private const PREFER_PAGE_TITLE = [
        'people.registrar.students.show',
        'communications.show',
        'reporting.show',
    ];

    /**
     * @param  array<string, string>  $moduleLabels  sidebar route name => label
     * @return list<array{label: string, url: string|null, current: bool, home: bool}>
     */
    public static function build(?string $routeName, ?User $user, array $moduleLabels, ?string $pageTitle, ?string $activeModuleRoute = null): array
    {
        $routeName = (string) $routeName;
        $dashboardUrl = RouteFacade::has('identity.dashboard') ? route('identity.dashboard') : url('/');

        if ($routeName === 'identity.dashboard') {
            return [['label' => 'Dashboard', 'url' => null, 'current' => true, 'home' => true]];
        }

        $currentLabel = self::currentLabel($routeName, $moduleLabels, $pageTitle);

        // Walk up the declared hierarchy, falling back to the sidebar module that
        // owns this page (e.g. a tab or action page inside that module).
        $ancestors = [];
        $parent = self::parentOf($routeName, $user);
        if ($parent === null && $activeModuleRoute !== null && $activeModuleRoute !== $routeName) {
            $parent = $activeModuleRoute;
        }

        $guard = 0;
        while ($parent !== null && $parent !== 'identity.dashboard' && $guard++ < 6) {
            if (! RouteFacade::has($parent)) {
                break;
            }
            array_unshift($ancestors, [
                'label' => $moduleLabels[$parent] ?? self::LABELS[$parent] ?? self::humanize($parent),
                'url' => route($parent),
                'current' => false,
                'home' => false,
            ]);
            $parent = self::parentOf($parent, $user);
        }

        $trail = [['label' => 'Dashboard', 'url' => $dashboardUrl, 'current' => false, 'home' => true]];
        foreach ($ancestors as $crumb) {
            $trail[] = $crumb;
        }
        $trail[] = ['label' => $currentLabel, 'url' => null, 'current' => true, 'home' => false];

        // Never show the same level twice (e.g. a module page titled like its module).
        $deduped = [];
        foreach ($trail as $crumb) {
            $last = end($deduped);
            if ($last !== false && strcasecmp($last['label'], $crumb['label']) === 0) {
                array_pop($deduped);
            }
            $deduped[] = $crumb;
        }

        return array_values($deduped);
    }

    private static function currentLabel(string $routeName, array $moduleLabels, ?string $pageTitle): string
    {
        $title = trim((string) $pageTitle);

        if (in_array($routeName, self::PREFER_PAGE_TITLE, true) && $title !== '') {
            return Str::limit($title, 60);
        }

        return $moduleLabels[$routeName]
            ?? self::LABELS[$routeName]
            ?? ($title !== '' ? Str::limit($title, 60) : self::humanize($routeName));
    }

    private static function parentOf(string $routeName, ?User $user): ?string
    {
        $parent = self::PARENTS[$routeName] ?? null;

        if (! is_array($parent)) {
            return $parent;
        }

        foreach ($parent as $role => $target) {
            if ($role !== '*' && $user !== null && $user->hasRole($role)) {
                return $target;
            }
        }

        return $parent['*'] ?? null;
    }

    private static function humanize(string $routeName): string
    {
        $segments = array_values(array_filter(
            explode('.', $routeName),
            fn (string $segment) => ! in_array($segment, ['index', 'show', 'create', 'edit'], true),
        ));

        return Str::headline((string) (end($segments) ?: 'Page'));
    }
}
