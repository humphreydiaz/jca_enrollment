@props(['slip', 'title' => 'Enrollment access slip', 'stepsTitle' => 'How to continue the enrollment', 'nextStep' => 'Complete and submit the enrollment application, including the parent or guardian details asked there.'])

{{-- One-page slip handed to the account owner. Inline styles: print-safe and independent of the screen CSS build. --}}
<x-print.letterhead maxHeight="28mm" />
<div style="border:2px solid #17164f;border-radius:6mm;padding:7mm 8mm;">
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:6mm;border-bottom:1px solid #d9dce7;padding-bottom:4mm;">
        <div>
            <div style="font-size:9pt;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#e97817;">{{ $title }}</div>
            <div style="font-size:18pt;font-weight:900;color:#17164f;margin-top:1mm;">{{ $slip['learner_name'] ?? '' }}</div>
            <div style="font-size:10.5pt;color:#444;margin-top:1mm;">{{ $slip['grade_level'] ?? '' }} · {{ $slip['school_year'] ?? '' }}</div>
        </div>
        @if (filled($slip['application_number'] ?? null))
        <div style="text-align:right;font-size:9pt;color:#444;">
            Application reference<br><strong style="font-size:11pt;color:#111;">{{ $slip['application_number'] }}</strong>
        </div>
        @endif
    </div>

    <table style="width:100%;border-collapse:collapse;margin-top:5mm;font-size:10.5pt;">
        <tr><td style="padding:1.5mm 0;color:#555;width:42%;">Account owner</td><td style="padding:1.5mm 0;font-weight:700;">{{ $slip['account_owner_name'] }} ({{ $slip['account_owner_type'] }})</td></tr>
        <tr><td style="padding:1.5mm 0;color:#555;">Student ID (sign-in ID)</td><td style="padding:1.5mm 0;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:16pt;font-weight:900;letter-spacing:.06em;">{{ $slip['student_number'] }}</td></tr>
        <tr><td style="padding:1.5mm 0;color:#555;">Temporary password</td><td style="padding:1.5mm 0;"><span style="display:inline-block;border:1.5px dashed #17164f;border-radius:2mm;padding:1.5mm 3mm;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:16pt;font-weight:900;letter-spacing:.08em;">{{ $slip['temporary_password'] }}</span></td></tr>
        <tr><td style="padding:1.5mm 0;color:#555;">Valid until</td><td style="padding:1.5mm 0;font-weight:700;">{{ $slip['expires_at'] }}</td></tr>
    </table>

    <div style="margin-top:5mm;font-size:10.5pt;line-height:1.5;">
        <div style="font-weight:800;color:#17164f;margin-bottom:1.5mm;">{{ $stepsTitle }}</div>
        <ol style="margin:0;padding-left:6mm;list-style:decimal;">
            <li>Go to <strong>{{ route('identity.login') }}</strong></li>
            <li>Sign in with the <strong>Student ID</strong> and the <strong>temporary password</strong> above.</li>
            <li>Create your own new password when asked. The temporary password then stops working.</li>
            @if ($nextStep)<li>{{ $nextStep }}</li>@endif
        </ol>
    </div>

    <div style="margin-top:5mm;padding:3mm 4mm;background:#fff7ed;border-radius:2mm;font-size:9.5pt;color:#7a2e0e;">
        Keep this slip private. The temporary password works only until it is replaced or until the date above. If it is lost or expires, ask the Registrar for a replacement.
    </div>

    <div style="margin-top:5mm;font-size:8.5pt;color:#666;">
        Issued{{ filled($slip['issued_by'] ?? null) ? ' by '.$slip['issued_by'] : '' }}{{ filled($slip['issued_at'] ?? null) ? ' on '.$slip['issued_at'] : '' }} · JCSGO Christian Academy Registrar's Office
    </div>
</div>
