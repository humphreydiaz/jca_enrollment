<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $definition['label'] }} report</title><style>body{font-family:Arial,sans-serif;color:#17203a;margin:28px;font-size:12px}header{border-bottom:3px solid #29267d;padding-bottom:12px;margin-bottom:18px}h1{margin:0;color:#17164f;font-size:24px}p{margin:5px 0}.notice{background:#fff5cc;border:1px solid #d4a900;padding:8px;margin:12px 0}table{border-collapse:collapse;width:100%}th,td{border:1px solid #cfd4df;padding:7px;text-align:left;vertical-align:top}th{background:#e8ecff;color:#17164f}thead{display:table-header-group}@media print{body{margin:10mm}.no-print{display:none}tr{break-inside:avoid}}/* Enrollment summary print styles */.es-wrap{max-width:700px;margin:0 auto}.es-header{display:flex;align-items:flex-start;gap:12px;border-bottom:3px solid #17164f;padding-bottom:14px;margin-bottom:24px}.es-header img{width:56px;height:56px;object-fit:contain}.es-school{text-align:center;flex:1}.es-school h2{font-size:18px;font-weight:900;color:#17164f;margin:0 0 3px}.es-school p{font-size:11px;color:#555;margin:0;line-height:1.5}.es-title{text-align:center;margin-bottom:28px}.es-title h3{font-size:20px;font-weight:900;color:#17164f;margin:0 0 2px}.es-title p{font-size:14px;font-weight:700;color:#17164f;margin:2px 0}.es-title .es-asof{font-size:16px;font-weight:900;margin-top:6px;letter-spacing:0.02em}.es-stage{margin-bottom:20px}.es-stage h4{font-size:13px;font-weight:800;color:#17164f;text-transform:uppercase;margin:0 0 8px}.es-stable{border-collapse:collapse;width:100%;margin-bottom:2px}.es-stable th{text-align:center;font-size:11px;font-weight:800;border:none;border-bottom:2px solid #17164f;padding:5px 10px;color:#17164f}.es-stable th:first-child{text-align:left;padding-left:24px;width:40%}.es-stable td{border:none;border-bottom:1px solid #ddd;padding:4px 10px 4px 32px;font-size:12px}.es-stable td.num{text-align:center;padding-left:10px;font-variant-numeric:tabular-nums}.es-stable td.num-bold{text-align:center;padding-left:10px;font-weight:800;font-variant-numeric:tabular-nums}.es-stable tfoot td{border-top:2px solid #bbb;border-bottom:2px solid #17164f;font-weight:700;color:#17164f;padding-top:5px;padding-bottom:5px}.es-grand{display:flex;align-items:center;padding:8px 10px;margin-bottom:16px}.es-grand-label{width:40%;text-align:right;padding-right:12px;font-size:13px;font-weight:900;color:#17164f}.es-grand-num{width:20%;text-align:center;font-size:14px;font-weight:700;color:#17164f;font-variant-numeric:tabular-nums}.es-grand-final{border:2px solid #17164f;border-radius:4px;margin-top:4px}.es-grand-final .es-grand-label{font-size:15px}.es-grand-final .es-grand-num{font-size:16px;font-weight:900}</style></head><body>
@if(isset($reportCode) && $reportCode === 'enrollments' && isset($enrollmentSummary))
    {{-- Enrollment Summary Print Layout --}}
    <div class="es-wrap">
        <div class="es-header">
            <img src="{{ asset('images/logo/jca_logo.png') }}" alt="">
            <div class="es-school">
                <h2>JCSGO CHRISTIAN ACADEMY R1 INC.</h2>
                <p>Almazan St. Libsong West, Lingayen, Pangasinan</p>
                <p>jcaregion1@yahoo.com</p>
                <p>Tel No. 075-542-4973</p>
            </div>
        </div>
        <div class="es-title">
            <h3>Enrolment Data</h3>
            @if($enrollmentSummary['school_year_name'])
                <p>S.Y. — {{ $enrollmentSummary['school_year_name'] }}</p>
            @endif
            <p class="es-asof">AS OF {{ strtoupper(now()->timezone(config('app.timezone'))->format('F Y')) }}</p>
        </div>

        @php
            $stageLabels = ['Pre-elementary' => 'A. PRE-ELEMENTARY', 'Elementary' => 'B. ELEMENTARY', 'Junior High School' => 'C. JUNIOR HIGH SCHOOL', 'Senior High School' => 'D. SENIOR HIGH SCHOOL'];
            $grandMale = 0; $grandFemale = 0; $grandTotal = 0;
            $elemGrandMale = 0; $elemGrandFemale = 0; $elemGrandTotal = 0;
        @endphp

        @foreach($enrollmentSummary['stages'] as $stageName => $grades)
            @php
                $stageMale = collect($grades)->sum('male');
                $stageFemale = collect($grades)->sum('female');
                $stageTotal = collect($grades)->sum('total');
                $grandMale += $stageMale; $grandFemale += $stageFemale; $grandTotal += $stageTotal;
                if (in_array($stageName, ['Pre-elementary', 'Elementary'])) {
                    $elemGrandMale += $stageMale; $elemGrandFemale += $stageFemale; $elemGrandTotal += $stageTotal;
                }
            @endphp
            <div class="es-stage">
                <h4>{{ $stageLabels[$stageName] ?? strtoupper($stageName) }}</h4>
                <table class="es-stable">
                    <thead><tr><th>Grade Level</th><th>Male</th><th>Female</th><th>Total</th></tr></thead>
                    <tbody>
                        @foreach($grades as $gradeRow)
                            <tr><td>{{ $gradeRow['grade'] }}</td><td class="num">{{ $gradeRow['male'] }}</td><td class="num">{{ $gradeRow['female'] }}</td><td class="num-bold">{{ $gradeRow['total'] }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><td style="text-align:right;font-style:italic">Total</td><td class="num">{{ $stageMale }}</td><td class="num">{{ $stageFemale }}</td><td class="num-bold">{{ $stageTotal }}</td></tr></tfoot>
                </table>
            </div>
            @if($stageName === 'Elementary')
                <div class="es-grand"><span class="es-grand-label">ELEM GRAND TOTAL</span><span class="es-grand-num">{{ $elemGrandMale }}</span><span class="es-grand-num">{{ $elemGrandFemale }}</span><span class="es-grand-num" style="font-weight:900">{{ $elemGrandTotal }}</span></div>
            @endif
            @if($stageName === 'Junior High School')
                <div class="es-grand"><span class="es-grand-label">JHS GRAND TOTAL</span><span class="es-grand-num">{{ $stageMale }}</span><span class="es-grand-num">{{ $stageFemale }}</span><span class="es-grand-num" style="font-weight:900">{{ $stageTotal }}</span></div>
            @endif
        @endforeach
        <div class="es-grand es-grand-final"><span class="es-grand-label">GRAND TOTAL</span><span class="es-grand-num">{{ $grandMale }}</span><span class="es-grand-num">{{ $grandFemale }}</span><span class="es-grand-num">{{ $grandTotal }}</span></div>
    </div>
    <p class="no-print" style="text-align:center;margin-top:24px"><button type="button" onclick="window.print()">Print</button></p>
@else
    {{-- Standard report print layout --}}
    <x-print.letterhead />
    <header><h1>{{ $definition['label'] }} report</h1><p>Generated {{ now()->timezone(config('app.timezone'))->format('d M Y, g:i A') }} · Confidential operational use</p></header>
    <p class="notice">Development operational layout. Official signatories, control numbers, and final format remain pending under P-006.</p>@if($truncated)<p class="notice"><strong>Result limited:</strong> this print view shows the first {{ config('reporting.print_row_limit') }} authorized rows. Use a filtered private CSV export for larger results.</p>@endif
    <table><thead><tr>@foreach($columns as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>@forelse($rows as $row)<tr>@foreach($columns as $field => $label)<td>{{ data_get($row, $field) ?? '—' }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($columns) }}">No records match the authorized filters.</td></tr>@endforelse</tbody></table>
    <p class="no-print"><button type="button" onclick="window.print()">Print</button></p>
@endif
</body></html>
