<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A per-entry-year counter that issues sequential student numbers. Keeping
     * the last issued value here — rather than deriving it from MAX(student_number)
     * — guarantees a number is never reused after a learner withdraws, cancels,
     * or is rejected, because the counter only ever moves forward.
     */
    public function up(): void
    {
        Schema::create('student_number_counters', function (Blueprint $table): void {
            $table->string('year_prefix', 9)->primary(); // e.g. "2026-2027"
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_number_counters');
    }
};
