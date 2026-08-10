@props(['inverse' => false])

<a href="{{ url('/') }}" class="inline-flex min-w-0 items-center gap-2.5 rounded-lg text-left" aria-label="JCSGO Christian Academy R1-Inc. Student Management System home">
    <img src="{{ asset('images/logo/jca_logo.png') }}" alt="" class="size-11 shrink-0 object-contain drop-shadow-sm" aria-hidden="true">
    <span class="w-32 min-w-0 leading-tight">
        <span @class(['block text-[0.68rem] font-extrabold uppercase leading-[0.85rem]', 'text-white' => $inverse, 'text-brand-navy-900' => ! $inverse])>
            <span class="block">JCSGO Christian</span>
            <span class="block">Academy R1-Inc.</span>
        </span>
        <span @class(['mt-0.5 block whitespace-nowrap text-[0.65rem] font-semibold', 'text-slate-300' => $inverse, 'text-muted' => ! $inverse])>Student Management System</span>
    </span>
</a>
