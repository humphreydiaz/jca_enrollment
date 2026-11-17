<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>School Payment Acknowledgment - {{ $payment->receipt_reference }}</title>
    <style>
        @page { size: A5 portrait; margin: 9mm; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; background: #edf1f7; color: #14264b; font: 12px/1.45 Arial, Helvetica, sans-serif; }
        .toolbar { max-width: 540px; margin: 0 auto 12px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .toolbar button { background: #24276e; color: #fff; border: 0; border-radius: 6px; padding: 10px 18px; font-weight: 700; cursor: pointer; }
        .toolbar span { color: #53627c; font-size: 11px; }
        .receipt { width: 100%; max-width: 540px; margin: 0 auto; background: #fff; border: 2px solid #1d2b5e; padding: 16px; box-shadow: 0 8px 25px rgba(0,0,0,.08); }
        .letterhead { text-align: center; max-height: 30mm; overflow: hidden; }
        .heading { text-align: center; padding: 8px 0 11px; border-bottom: 2px solid #1d2b5e; }
        .heading h1 { margin: 0; font-size: 18px; letter-spacing: .07em; color: #172554; }
        .heading p { margin: 4px 0 0; font-size: 10px; color: #52617a; }
        .meta { display: flex; justify-content: space-between; gap: 12px; padding: 12px 0; border-bottom: 1px dashed #aab5c9; }
        .meta .right { text-align: right; }
        .caption { color: #64748b; font-size: 10px; display: block; }
        .value { font-weight: 700; overflow-wrap: anywhere; }
        .copy { display: inline-block; margin-top: 6px; padding: 3px 8px; border: 1px solid #172554; font-size: 10px; font-weight: 800; letter-spacing: .04em; }
        .details { padding: 11px 0; }
        .details table { width: 100%; border-collapse: collapse; }
        .details th { width: 38%; padding: 5px 8px 5px 0; text-align: left; font-weight: 500; color: #52617a; vertical-align: top; }
        .details td { padding: 5px 0; font-weight: 700; vertical-align: top; overflow-wrap: anywhere; }
        .amount { background: #edf2fa; border-top: 2px solid #172554; border-bottom: 2px solid #172554; padding: 12px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .amount span { font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .amount strong { font-size: 21px; white-space: nowrap; }
        .allocation { text-align: right; margin-top: 8px; font-size: 10px; color: #52617a; }
        .signatures { display: flex; justify-content: space-between; gap: 18px; margin-top: 38px; }
        .signature { flex: 1; text-align: center; min-width: 0; }
        .signature .line { border-bottom: 1px solid #1d2b5e; min-height: 17px; font-weight: 700; overflow-wrap: anywhere; }
        .signature small { display: block; padding-top: 4px; font-size: 9px; color: #52617a; }
        .footer { margin-top: 22px; padding-top: 9px; border-top: 1px dashed #aab5c9; text-align: center; color: #52617a; font-size: 9px; }
        @media print {
            html, body { background: #fff !important; margin: 0; padding: 0; }
            .toolbar { display: none !important; }
            .receipt { width: 100%; max-width: none; margin: 0; box-shadow: none; break-inside: avoid; }
            .amount { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>Print on A5 paper or save as PDF</span>
        <button type="button" onclick="window.print()">Print Receipt</button>
    </div>

    <article class="receipt">
        <div class="letterhead">
            <x-print.letterhead max-height="30mm" />
        </div>

        <header class="heading">
            <h1>PAYMENT ACKNOWLEDGMENT</h1>
            <p>School Collection Receipt &bull; Not an Official Receipt</p>
        </header>

        <section class="meta">
            <div>
                <span class="caption">Receipt Number</span>
                <div class="value">{{ $payment->receipt_reference }}</div>
                <span class="copy">{{ $print->print_number === 1 ? 'ORIGINAL COPY' : 'REPRINT #'.($print->print_number - 1) }}</span>
            </div>
            <div class="right">
                <span class="caption">Date Received</span>
                <div class="value">{{ $payment->received_on?->format('M j, Y') ?? '—' }}</div>
                <span class="caption" style="margin-top:6px">Payment Reference</span>
                <div class="value">{{ $payment->payment_reference }}</div>
            </div>
        </section>

        <section class="details">
            <table>
                <tr><th>Student Name</th><td>{{ $payment->assessment->enrollment->student->full_name }}</td></tr>
                <tr><th>Received From</th><td>{{ $payment->payer_name }}</td></tr>
                <tr><th>Payment Method</th><td>{{ str($payment->method)->headline() }}</td></tr>
                <tr><th>Recorded By</th><td>{{ $payment->poster?->name ?? 'Accounting' }}</td></tr>
            </table>
        </section>

        <div class="amount">
            <span>Total Amount Paid</span>
            <strong>PHP {{ number_format((float) $payment->amount, 2) }}</strong>
        </div>
        <div class="allocation">Allocated Amount: PHP {{ number_format((float) $payment->allocations->sum('amount'), 2) }}</div>

        <div class="signatures">
            <div class="signature">
                <div class="line">{{ $payment->poster?->name ?? 'Accounting' }}</div>
                <small>Received / Recorded By</small>
            </div>
            <div class="signature">
                <div class="line">&nbsp;</div>
                <small>Authorized Signature</small>
            </div>
        </div>

        <footer class="footer">
            This document acknowledges an offline school payment recorded in JCA-ESIS.<br>
            It is not an official receipt or tax invoice. Retain a copy for payment reference.
        </footer>
    </article>
</body>
</html>
