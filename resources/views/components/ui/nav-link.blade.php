
@props([
    'href',
    'icon',
    'active' => false,
    'notification' => false,
    'notificationKey' => null,
])

<a
    href="{{ $href }}"
    @if ($active)
        aria-current="page"
    @endif

    @if ($notificationKey)
        x-data="{
            notificationVisible: @js((bool) $notification),
            navigating: false,

            async openNotification(event) {
                if (!this.notificationVisible || this.navigating) {
                    return;
                }

                if (
                    event.button !== 0 ||
                    event.ctrlKey ||
                    event.metaKey ||
                    event.shiftKey ||
                    event.altKey
                ) {
                    return;
                }

                event.preventDefault();

                this.navigating = true;

                try {
                    const response = await fetch(
                        @js(route('sidebar.notifications.mark-seen')),
                        {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN':
                                    document.querySelector('meta[name=csrf-token]')?.content
                                    ?? @js(csrf_token())
                            },
                            body: JSON.stringify({
                                notification_key: @js($notificationKey)
                            })
                        }
                    );

                    if (response.ok) {
                        this.notificationVisible = false;
                    }
                } catch (error) {
                    console.error('Notification update failed:', error);
                }

                window.location.assign(this.$el.href);
            }
        }"
        @click="openNotification($event)"
    @endif

    {{ $attributes->class([
        'group flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
        'bg-brand-navy-700 text-white shadow-sm' => $active,
        'text-slate-700 hover:bg-brand-blue-100 hover:text-brand-navy-900' => ! $active,
    ]) }}

    style="font-size: 16px; font-weight: 600;"
>
    <span
        style="
            position: relative;
            display: inline-flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
        "
    >
        <x-ui.icon
            :name="$icon"
            class="{{ $active
                ? 'text-brand-gold-500'
                : 'text-slate-400 group-hover:text-brand-navy-700'
            }}"
        />

        @if ($notification)
            <span
                @if ($notificationKey)
                    x-show="notificationVisible"
                    x-cloak
                @endif

                style="
                    position: absolute;
                    top: -3px;
                    right: -3px;
                    width: 10px;
                    height: 10px;
                    background-color: #ef4444;
                    border-radius: 50%;
                    border: 1.5px solid white;
                    z-index: 10;
                    pointer-events: none;
                "

                aria-label="New activity"
            ></span>
        @endif
    </span>

    <span class="min-w-0 flex-1">
        {{ $slot }}
    </span>
</a>
