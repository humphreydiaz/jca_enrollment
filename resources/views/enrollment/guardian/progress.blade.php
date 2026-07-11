<x-layouts.app title="My enrollment progress">
    <section class="jca-card p-6">
        <h1 class="text-3xl font-black text-brand-navy-900">My enrollment progress</h1>
        <p class="mt-2 text-sm text-muted">Only safe progress for children connected to this guardian account appears here.</p>
        <div class="mt-6 space-y-5">
            @forelse($enrollments as $enrollment)
                <article class="rounded-lg border border-border p-4">
                    <h2 class="font-bold">{{ $enrollment->student->full_name }} · {{ $enrollment->academicYear->name }}</h2>
                    <p class="mt-1 text-sm text-muted">Reference {{ $enrollment->application_number }} · {{ ucfirst(str_replace('_',' ',$enrollment->status)) }} · {{ $enrollment->gradeLevel->name }}</p>
                    <div class="mt-3 space-y-3">@foreach($enrollment->requirements as $requirement)<section class="rounded-lg bg-canvas p-3"><p class="text-sm">{{ $requirement->documentType->name }}: <strong>{{ ucfirst(str_replace('_',' ',$requirement->status)) }}</strong></p>@php($currentDocument = $requirement->uploads->first(fn($upload) => $upload->archived_at === null && $upload->deleted_at === null))@if($currentDocument)<a class="mt-2 inline-block text-sm font-bold text-brand-navy-700 underline" href="{{ route('requirements.documents.download',$currentDocument) }}">Download current upload</a>@endif @if(in_array($enrollment->status,['draft','submitted','incomplete'],true))<form method="POST" enctype="multipart/form-data" action="{{ route('requirements.documents.store',$requirement) }}" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">@csrf<label class="text-sm font-semibold">{{ $currentDocument ? 'Replace document' : 'Upload document' }}<input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full text-sm"></label><x-ui.button type="submit" variant="secondary">{{ $currentDocument ? 'Upload replacement' : 'Upload' }}</x-ui.button></form><p class="mt-1 text-xs text-muted">PDF, JPG, or PNG; maximum 5 MB. Replacements preserve prior versions.</p>@endif</section>@endforeach</div>
                    @if($enrollment->requirements->whereIn('status',['missing','rejected'])->isNotEmpty())<p class="mt-3 rounded-lg bg-warning-100 p-3 text-sm text-warning-800">Some requirements still need attention. Contact JCA or replace a rejected document through your authorized requirement link.</p>@endif
                </article>
            @empty
                <p class="text-muted">No linked enrollment progress is available.</p>
            @endforelse
        </div>
    </section>
</x-layouts.app>
