@props([
    'maxHeight' => '45mm',
])

<div {{ $attributes->merge(['style' => 'width:100%;margin:0 0 5mm;text-align:center;']) }}>
    <img
        src="{{ asset('images/reporting/JCA-reports-header.png') }}"
        alt="JCSGO Christian Academy official letterhead"
        style="display:block;width:100%;height:auto;max-height:{{ $maxHeight }};object-fit:contain;object-position:center;"
    >
</div>
