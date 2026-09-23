<x-layouts.app :title="$student->full_name">
    <div class="space-y-6">
        <x-ui.page-header
            title="Learner record"
            description="The complete profile submitted through the enrollment application, the guardians on record, and every application filed for this learner."
            eyebrow="Registrar workspace"
        >
            <x-slot:actions>
                <a href="{{ route('people.registrar.students.index') }}" class="jca-button jca-button-secondary">
                    <x-ui.icon name="arrow-right" size="size-4" class="rotate-180" />
                    Back to directory
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="jca-card p-5 sm:p-6">
            @include('people.registrar.students.partials.record')
        </div>
    </div>
</x-layouts.app>
