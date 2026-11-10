<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('mf_billing_accounts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('student_id')->unique()->constrained('students')->restrictOnDelete();
            $t->unsignedInteger('amount_cents');
            $t->unsignedTinyInteger('due_day');
            $t->char('start_month', 7);
            $t->boolean('enabled')->default(true);
            $t->text('whatsapp_phone')->nullable(); // encrypted
            $t->char('phone_hash', 64)->nullable()->index();
            $t->timestamp('consented_at')->nullable();
            $t->text('consent_note')->nullable(); // encrypted
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
        });
        Schema::create('mf_billing_invoices', function (Blueprint $t): void {
            $t->id(); $t->uuid('uuid')->unique();
            $t->foreignId('account_id')->constrained('mf_billing_accounts')->restrictOnDelete();
            $t->char('reference_month', 7);
            $t->date('due_date'); $t->unsignedInteger('amount_cents');
            $t->date('paid_on')->nullable();
            $t->timestamp('payment_recorded_at')->nullable();
            $t->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('payment_method', 20)->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
            $t->unique(['account_id','reference_month'], 'mf_invoice_account_month');
            $t->index(['reference_month','due_date','paid_on']);
        });
        Schema::create('mf_billing_reminders', function (Blueprint $t): void {
            $t->id(); $t->uuid('uuid')->unique(); $t->uuid('idempotency_key')->unique();
            $t->foreignId('invoice_id')->constrained('mf_billing_invoices')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('channel', 16); $t->string('status', 32);
            $t->text('recipient'); $t->text('message'); // encrypted
            $t->unsignedInteger('invoice_revision');
            $t->timestamp('reviewed_at');
            $t->string('provider_id', 255)->nullable()->index();
            $t->string('error_code', 80)->nullable();
            $t->timestamp('provider_at')->nullable();
            $t->timestamps();
            $t->index(['invoice_id','created_at']);
        });
        Schema::create('mf_billing_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('account_id')->constrained('mf_billing_accounts')->restrictOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained('mf_billing_invoices')->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('action', 48); $t->text('details'); // encrypted:array
            $t->timestamp('created_at');
            $t->index(['account_id','created_at']);
        });
    }
    public function down(): void {
        // Não executar em produção com histórico financeiro. Use feature flag.
        Schema::dropIfExists('mf_billing_events');
        Schema::dropIfExists('mf_billing_reminders');
        Schema::dropIfExists('mf_billing_invoices');
        Schema::dropIfExists('mf_billing_accounts');
    }
};
