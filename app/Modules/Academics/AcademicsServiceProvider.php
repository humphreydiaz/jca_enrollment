<?php

namespace App\Modules\Academics;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Academics\Policies\AcademicYearPolicy;
use App\Modules\Academics\Policies\GradeLevelPolicy;
use App\Modules\Academics\Policies\SectionPolicy;
use App\Modules\Academics\Policies\SubjectPolicy;
use App\Modules\Academics\Policies\TeachingAssignmentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AcademicsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(AcademicYear::class, AcademicYearPolicy::class);
        Gate::policy(GradeLevel::class, GradeLevelPolicy::class);
        Gate::policy(Subject::class, SubjectPolicy::class);
        Gate::policy(Section::class, SectionPolicy::class);
        Gate::policy(TeachingAssignment::class, TeachingAssignmentPolicy::class);

        Route::middleware('web')->group(base_path('routes/modules/academics.php'));
    }
}
