<?php

namespace App\Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;

class NextFinancialReferenceAction
{
    public function execute(string $type, string $prefix): string
    {
        return DB::transaction(function () use ($type, $prefix): string {
            $year = (int) now()->format('Y');
            DB::table('financial_sequences')->insertOrIgnore(['sequence_type' => $type, 'sequence_year' => $year, 'last_value' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $row = DB::table('financial_sequences')->where(['sequence_type' => $type, 'sequence_year' => $year])->lockForUpdate()->first();
            $number = $row->last_value + 1;
            DB::table('financial_sequences')->where('id', $row->id)->update(['last_value' => $number, 'updated_at' => now()]);

            return $prefix.'-'.$year.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
        });
    }
}
