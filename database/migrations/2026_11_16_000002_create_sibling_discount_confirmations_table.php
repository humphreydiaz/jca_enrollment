<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-067: the Registrar confirms that a learner qualifies for the sibling discount (another learner of the
 * same verified parent/guardian is enrolled in the same school year). Accounting then applies the school's
 * sibling discount through the approved-adjustment flow. Confirmations are never deleted: a withdrawal keeps
 * the row with who withdrew it and why, and active_key allows only one active confirmation per enrollment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sibling_discount_confirmations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('active_key')->nullable()->unique();
            $table->string('sibling_summary', 500);
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('withdraw_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['enrollment_id', 'withdrawn_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sibling_discount_confirmations');
    }
};
