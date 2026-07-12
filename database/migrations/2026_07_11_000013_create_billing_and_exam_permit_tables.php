<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('category', 60);
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_conditional')->default(false);
            $table->boolean('is_permit_critical')->default(false);
            $table->unsignedSmallInteger('max_quantity')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('fee_schedules', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft')->index();
            $table->date('effective_on')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['academic_year_id', 'grade_level_id', 'version'], 'fee_schedule_version_unique');
        });
        Schema::create('fee_schedule_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_type_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_default_selected')->default(false);
            $table->timestamps();
            $table->unique(['fee_schedule_id', 'fee_type_id']);
        });
        Schema::create('fee_assessments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('assessment_reference', 40)->unique();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('previous_assessment_id')->nullable()->constrained('fee_assessments')->restrictOnDelete();
            $table->unsignedInteger('revision_number')->default(1);
            $table->string('status', 20)->default('draft')->index();
            $table->string('currency', 3)->default('PHP');
            $table->decimal('gross_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('adjustment_total', 12, 2)->default(0);
            $table->decimal('net_total', 12, 2)->default(0);
            $table->text('revision_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->string('current_finalized_key', 80)->nullable()->unique();
            $table->timestamps();
            $table->unique(['enrollment_id', 'revision_number']);
            $table->index(['enrollment_id', 'status']);
        });
        Schema::create('assessment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fee_code', 20);
            $table->string('fee_name');
            $table->string('category', 60);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_amount', 12, 2);
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->boolean('is_permit_critical')->default(false);
            $table->timestamps();
            $table->index(['fee_assessment_id', 'is_permit_critical']);
        });
        Schema::create('payment_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_assessment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('plan_type', 30);
            $table->string('status', 20)->default('active');
            $table->foreignId('selected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('selected_at');
            $table->timestamps();
        });
        Schema::create('payment_plan_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['academic_year_id', 'grade_level_id', 'code'], 'payment_plan_option_unique');
        });
        Schema::create('school_non_business_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->date('calendar_date');
            $table->string('label');
            $table->timestamps();
            $table->unique(['academic_year_id', 'calendar_date']);
        });
        Schema::create('payment_schedule_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_plan_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->string('label');
            $table->date('due_on');
            $table->decimal('amount', 12, 2);
            $table->decimal('permit_critical_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['payment_plan_id', 'installment_number']);
            $table->index(['due_on', 'permit_critical_amount']);
        });
        Schema::create('payment_to_follow_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_assessment_id')->constrained()->restrictOnDelete();
            $table->date('approved_until');
            $table->text('reason')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->boolean('is_extension')->default(false);
            $table->timestamp('approved_at');
            $table->timestamps();
        });
        Schema::create('discount_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('value_type', 20);
            $table->decimal('value_amount', 12, 2);
            $table->unsignedInteger('slot_limit')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_development_fixture')->default(false);
            $table->timestamps();
            $table->unique(['academic_year_id', 'code']);
        });
        Schema::create('discount_awards', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('discount_campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_assessment_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('rank');
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('awarded');
            $table->timestamp('awarded_at');
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->unique(['discount_campaign_id', 'rank']);
            $table->unique(['discount_campaign_id', 'enrollment_id']);
        });
        Schema::create('discount_award_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discount_award_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 30);
            $table->text('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
        Schema::create('financial_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('sequence_type', 30);
            $table->unsignedSmallInteger('sequence_year');
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
            $table->unique(['sequence_type', 'sequence_year']);
        });
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('payment_reference', 40)->nullable()->unique();
            $table->string('receipt_reference', 40)->nullable()->unique();
            $table->foreignId('fee_assessment_id')->constrained()->restrictOnDelete();
            $table->date('received_on');
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('payer_name');
            $table->string('method_reference', 100)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('method_description', 100)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['method', 'method_reference']);
            $table->index(['fee_assessment_id', 'status', 'received_on']);
        });
        Schema::create('payment_evidence', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('disk', 60);
            $table->string('storage_path', 500)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_schedule_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->index(['payment_id', 'assessment_item_id']);
        });
        Schema::create('credit_allocations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('source_payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('target_assessment_item_id')->constrained('assessment_items')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->foreignId('allocated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('allocated_at');
            $table->timestamps();
            $table->index(['source_payment_id', 'target_assessment_item_id'], 'credit_allocation_lookup');
        });
        Schema::create('payment_reversals', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->string('status', 20)->default('requested')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
        Schema::create('payment_reversal_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_reversal_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_allocation_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['payment_reversal_id', 'payment_allocation_id'], 'reversal_allocation_unique');
        });
        Schema::create('financial_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('fee_assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('adjustment_type', 30);
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->string('status', 20)->default('requested')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('fee_assessment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->string('status', 20)->default('requested');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->string('release_reference', 100)->nullable();
            $table->date('released_on')->nullable();
            $table->timestamps();
        });
        Schema::create('receipt_prints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('print_number');
            $table->foreignId('printed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('printed_at');
            $table->timestamps();
            $table->unique(['payment_id', 'print_number']);
        });
        Schema::create('exam_periods', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('eligibility_cutoff_on');
            $table->date('issue_opens_on')->nullable();
            $table->date('issue_closes_on')->nullable();
            $table->string('status', 20)->default('planned');
            $table->timestamps();
            $table->unique(['academic_year_id', 'code']);
        });
        Schema::create('exam_permit_overrides', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reason_category', 60);
            $table->text('confidential_reason');
            $table->string('supporting_document_reference', 255)->nullable();
            $table->date('effective_from');
            $table->date('expires_on')->nullable();
            $table->string('status', 20)->default('requested');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('exam_permits', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('permit_reference', 40)->unique();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('override_id')->nullable()->constrained('exam_permit_overrides')->restrictOnDelete();
            $table->foreignId('replaces_permit_id')->nullable()->constrained('exam_permits')->restrictOnDelete();
            $table->decimal('required_amount', 12, 2);
            $table->decimal('satisfied_amount', 12, 2);
            $table->timestamp('calculated_at');
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('issued');
            $table->string('active_permit_key', 80)->nullable()->unique();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
            $table->index(['enrollment_id', 'exam_period_id', 'status']);
        });
        Schema::create('exam_permit_calculations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_assessment_id')->constrained()->restrictOnDelete();
            $table->decimal('required_amount', 12, 2);
            $table->decimal('satisfied_amount', 12, 2);
            $table->boolean('ordinary_eligible');
            $table->boolean('override_eligible')->default(false);
            $table->timestamp('calculated_at');
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['enrollment_id', 'exam_period_id', 'calculated_at'], 'permit_calculation_lookup');
        });
        Schema::create('exam_permit_prints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_permit_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('print_number');
            $table->foreignId('printed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('printed_at');
            $table->timestamps();
            $table->unique(['exam_permit_id', 'print_number']);
        });
        Schema::create('financial_print_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('setting_key', 60);
            $table->text('setting_value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['academic_year_id', 'setting_key']);
        });
    }

    public function down(): void
    {
        foreach (['financial_print_settings', 'exam_permit_prints', 'exam_permit_calculations', 'exam_permits', 'exam_permit_overrides', 'exam_periods', 'receipt_prints', 'refunds', 'financial_adjustments', 'payment_reversal_allocations', 'payment_reversals', 'credit_allocations', 'payment_allocations', 'payment_evidence', 'payments', 'financial_sequences', 'discount_award_events', 'discount_awards', 'discount_campaigns', 'payment_to_follow_approvals', 'payment_schedule_items', 'school_non_business_days', 'payment_plan_options', 'payment_plans', 'assessment_items', 'fee_assessments', 'fee_schedule_items', 'fee_schedules', 'fee_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
