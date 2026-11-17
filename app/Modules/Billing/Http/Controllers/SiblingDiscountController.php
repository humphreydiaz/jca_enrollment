<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\ConfirmSiblingDiscountAction;
use App\Modules\Billing\Actions\WithdrawSiblingDiscountAction;
use App\Modules\Billing\Http\Requests\SiblingDiscountRequest;
use App\Modules\Billing\Models\SiblingDiscountConfirmation;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Http\RedirectResponse;

/** D-067: sibling discount confirmation from the Family links page. */
class SiblingDiscountController
{
    public function store(Enrollment $enrollment, SiblingDiscountRequest $request, ConfirmSiblingDiscountAction $confirm): RedirectResponse
    {
        $confirm->execute($enrollment, $request->user());

        return back()->with('status', 'Sibling discount confirmed. Accounting will apply it to the assessment.');
    }

    public function destroy(SiblingDiscountConfirmation $confirmation, SiblingDiscountRequest $request, WithdrawSiblingDiscountAction $withdraw): RedirectResponse
    {
        $withdraw->execute($confirmation, $request->validated('reason'), $request->user());

        return back()->with('status', 'Sibling discount confirmation withdrawn. The record and reason are kept.');
    }
}
