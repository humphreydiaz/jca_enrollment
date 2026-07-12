<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentEvidence;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class StorePaymentEvidenceAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(Payment $payment, UploadedFile $file, User $actor): PaymentEvidence
    {
        return DB::transaction(function () use ($payment, $file, $actor): PaymentEvidence {
            $extension = strtolower($file->getClientOriginalExtension());
            $name = (string) Str::uuid().'.'.$extension;
            $path = 'billing/payments/'.$payment->public_id.'/'.$name;
            Storage::disk('documents')->putFileAs(dirname($path), $file, basename($path));
            $evidence = PaymentEvidence::query()->create(['payment_id' => $payment->id, 'disk' => 'documents', 'storage_path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => (string) $file->getMimeType(), 'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'uploaded_by' => $actor->id]);
            $this->audit->record($actor, 'billing.payment_evidence.uploaded', $evidence);

            return $evidence;
        });
    }
}
