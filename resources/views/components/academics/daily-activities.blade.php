@props(['section'])

{{-- A section's activity schedule (the preschool class program), one item per line. --}}
<div {{ $attributes->merge(['class' => 'text-sm leading-6 text-muted']) }}>
    <p class="font-bold text-brand-navy-900">Daily activities:</p>
    <ul class="mt-1">
        @foreach ($section->activityLines() as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ul>
</div>
