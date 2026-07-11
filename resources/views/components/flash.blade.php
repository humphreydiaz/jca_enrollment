@if (session('status'))
    <div class="jca-status jca-status-ready" role="status">
        <span aria-hidden="true">&#10003;</span>
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-5 rounded-lg border border-brand-red-600 bg-red-50 p-4 text-brand-red-600" role="alert" tabindex="-1">
        <p class="font-bold">Please correct the following:</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@if (session('invitation_url') && ! request()->routeIs('identity.admin.users.index'))
    <div class="mb-5 rounded-lg border border-brand-gold-500 bg-warning-100 p-4 text-warning-800">
        <p class="font-bold">One-time invitation link</p>
        <p class="mt-1 break-all text-sm"><a class="underline" href="{{ session('invitation_url') }}">{{ session('invitation_url') }}</a></p>
        <p class="mt-1 text-xs">Deliver only through the verified contact channel. The link expires and can be used once.</p>
    </div>
@endif
