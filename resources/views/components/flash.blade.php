@php $flashMessage = session('status') ?? session('success'); @endphp
@if ($flashMessage)
    {{-- Transient success toast: fixed to the viewport corner so it never scrolls
         the page or shifts layout. Auto-dismisses; also closable. Vanilla JS so it
         works in every layout regardless of whether Alpine is loaded. --}}
    <div
        id="jca-toast"
        class="fixed bottom-5 right-5 z-50 flex max-w-sm items-start gap-3 rounded-xl border border-emerald-200 bg-white p-4 shadow-2xl transition-all duration-300"
        role="status"
        aria-live="polite"
    >
        <x-ui.icon name="check" size="size-5" class="mt-0.5 shrink-0 text-success-700" />
        <p class="text-sm font-bold text-success-700">{{ $flashMessage }}</p>
        <button type="button" data-toast-close class="-mr-1 -mt-1 ml-1 shrink-0 rounded p-1 text-muted hover:bg-slate-100 hover:text-ink" aria-label="Dismiss notification">
            <x-ui.icon name="close" size="size-4" />
        </button>
    </div>
    <script>
        (function () {
            var toast = document.getElementById('jca-toast');
            if (! toast) {
                return;
            }
            var dismiss = function () {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(12px)';
                setTimeout(function () { toast.remove(); }, 300);
            };
            toast.querySelector('[data-toast-close]').addEventListener('click', dismiss);
            setTimeout(dismiss, 6000);
        })();
    </script>
@endif

@if ($errors->any())
    <div data-flash-errors class="mb-5 rounded-lg border border-brand-red-600 bg-red-50 p-4 text-brand-red-600" role="alert" tabindex="-1">
        <p class="font-bold">Please correct the following:</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@if (session('invitation_url') && ! request()->routeIs('identity.admin.users.index'))
    <div class="mb-5 rounded-lg border border-brand-gold-500 bg-warning-100 p-4 text-warning-800">
        <p class="font-bold">One-time account setup fallback</p>
        @if (session('invitation_code'))
            <p class="mt-2 text-sm">Account code: <strong class="font-mono tracking-wider">{{ session('invitation_code') }}</strong></p>
        @endif
        <p class="mt-1 break-all text-sm"><a class="underline" href="{{ session('invitation_url') }}">{{ session('invitation_url') }}</a></p>
        <p class="mt-1 text-xs">Use this only if the SMS does not arrive. Deliver it through the verified contact channel; it expires and can be used once.</p>
    </div>
@endif
