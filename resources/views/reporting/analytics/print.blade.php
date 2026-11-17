<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>School insights</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background: #fff; }
        @media print {
            .no-print, details { display: none !important; }
            .jca-card { break-inside: avoid; box-shadow: none !important; }
            @page { margin: 12mm; }
        }
    </style>
</head>
<body class="p-6 font-sans text-ink">
    <x-print.letterhead />
    @if ($insights)
        <header class="mb-4 mt-2 border-b-2 border-brand-navy-900 pb-3">
            <h1 class="text-2xl font-black text-brand-navy-900">School insights — {{ $insights['year']->name }}</h1>
            <p class="text-xs text-muted">Generated {{ now()->timezone(config('app.timezone'))->format('d M Y, g:i A') }} · Prepared by {{ auth()->user()->name }} · Confidential: for the school owner and administration.</p>
        </header>
        <div class="space-y-5">
            @include('reporting.analytics.partials.body')
        </div>
        <div class="mt-10 grid grid-cols-3 gap-8 text-center text-xs text-muted">
            <div class="border-t border-ink pt-1">Prepared by</div>
            <div class="border-t border-ink pt-1">Checked by</div>
            <div class="border-t border-ink pt-1">Noted by</div>
        </div>
    @endif
    <p class="no-print mt-6 text-center"><button type="button" class="jca-button jca-button-primary" onclick="window.print()">Print</button></p>
</body>
</html>
