<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_schedule_items', function (Blueprint $table) {
            $table->string('price_status', 20)
                ->default('final')
                ->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('fee_schedule_items', function (Blueprint $table) {
            $table->dropColumn('price_status');
        });
    }
};
