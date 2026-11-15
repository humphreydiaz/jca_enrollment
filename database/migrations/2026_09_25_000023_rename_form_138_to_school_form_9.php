<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_types')
            ->where('code', 'FORM_138')
            ->update([
                'name' => 'School Form 9 (SF9)',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('document_types')
            ->where('code', 'FORM_138')
            ->where('name', 'School Form 9 (SF9)')
            ->update([
                'name' => 'Form 138 / Report Card',
                'updated_at' => now(),
            ]);
    }
};
