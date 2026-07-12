<x-layouts.app title="Exam authorization">
    <section class="jca-card p-6">
        <h1 class="text-2xl font-black text-brand-navy-900">Exam authorization</h1>
        <p class="mt-2">{{ $enrollment->student->full_name }}</p>
        <p class="mt-5 text-lg font-bold">{{ $permit ? 'Authorized for exam' : 'Not yet authorized' }}</p>
        @if ($permit)<p class="mt-2">Permit number: {{ $permit->permit_reference }}</p>@endif
    </section>
</x-layouts.app>
