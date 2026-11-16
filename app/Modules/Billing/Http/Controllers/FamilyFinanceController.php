<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Queries\FamilyFinancialSummaryQuery;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** D-064: "My finances" for parents/guardians and students (pre-final panel: "financial summary ... to monitor their payment"). */
class FamilyFinanceController
{
    public function __invoke(Request $request, FamilyFinancialSummaryQuery $summaries, AuditRecorder $audit): View
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['guardian', 'student']), 403);

        $rows = $summaries->forUser($user);
        foreach ($rows as $row) {
            $audit->record($user, 'billing.financial_summary.viewed', $row['assessment'], ['view' => 'family']);
        }

        return view('billing.family.index', [
            'summaries' => $rows,
            'isGuardian' => $user->hasRole('guardian'),
        ]);
    }
}
