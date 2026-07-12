<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\StorePaymentEvidenceAction;
use App\Modules\Billing\Http\Requests\StorePaymentEvidenceRequest;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentEvidence;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentEvidenceController
{
    public function store(Payment $payment, StorePaymentEvidenceRequest $request, StorePaymentEvidenceAction $store): RedirectResponse
    {
        $store->execute($payment, $request->file('evidence'), $request->user());

        return back()->with('status', 'Private payment evidence stored.');
    }

    public function download(PaymentEvidence $evidence, Request $request, AuditRecorder $audit): StreamedResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'accounting']), 404);
        $audit->record($request->user(), 'billing.payment_evidence.downloaded', $evidence);

        return Storage::disk($evidence->disk)->download($evidence->storage_path, $evidence->original_name, ['Content-Type' => $evidence->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }
}
