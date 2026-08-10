<x-layouts.app title="Exam authorization">
    <section class="jca-card p-6">
        <h1 class="text-2xl font-black text-brand-navy-900">Exam authorization</h1>
        <p class="mt-2">{{ $enrollment->student->full_name }}</p>
        <div class="mt-5"><x-ui.status-badge :status="$permit ? 'authorized' : 'not_authorized'" :label="$permit ? 'Authorized for exam' : 'Not yet authorized'" /></div>
        @if ($permit)<p class="mt-2">Permit number: {{ $permit->permit_reference }}</p>@endif
    </section>
</x-layouts.app>
