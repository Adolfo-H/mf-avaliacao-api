<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Apenas tabelas NOVAS. Não reescreve mensalidades, alunos ou avaliações.
        Schema::create('mf_billing_auto_settings', function (Blueprint $t): void {
            $t->id();
            $t->boolean('enabled')->default(false);
            $t->text('sender_phone')->nullable(); // Eloquent encrypted
            $t->unsignedTinyInteger('first_after_days')->default(3);
            $t->unsignedTinyInteger('repeat_interval_days')->default(5);
            $t->unsignedTinyInteger('max_reminders')->default(1);
            $t->char('send_time', 5)->default('09:00');
            $t->text('templates'); // Eloquent encrypted:array
            $t->unsignedInteger('revision')->default(1);
            $t->text('worker_phone')->nullable(); // Eloquent encrypted
            $t->boolean('worker_connected')->default(false);
            $t->timestamp('worker_seen_at')->nullable();
            $t->timestamp('last_generated_at')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('mf_billing_auto_outbox', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('invoice_id')->constrained('mf_billing_invoices')->restrictOnDelete();
            $t->unsignedTinyInteger('step');
            $t->string('status', 24); // reserved/sending/sent/unknown/not_sent/cancelled
            $t->text('recipient'); // encrypted
            $t->text('sender_phone'); // encrypted
            $t->text('message'); // encrypted
            $t->unsignedInteger('invoice_revision');
            $t->unsignedInteger('account_revision');
            $t->unsignedInteger('settings_revision');
            $t->timestamp('claimed_at');
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->string('provider_id', 255)->nullable();
            $t->string('error_code', 80)->nullable();
            $t->timestamps();
            $t->unique(['invoice_id', 'step'], 'mf_auto_invoice_step_unique');
            $t->index(['status', 'claimed_at'], 'mf_auto_status_claimed_idx');
        });
    }

    public function down(): void
    {
        // Somente para testes descartáveis. NUNCA executar down/rollback com dados reais.
        Schema::dropIfExists('mf_billing_auto_outbox');
        Schema::dropIfExists('mf_billing_auto_settings');
    }
};
