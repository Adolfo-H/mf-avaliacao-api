<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Adição somente: históricos de alunos, faturas e mensagens são preservados.
        Schema::table('mf_billing_accounts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('max_reminders')->default(1);
            $table->unsignedTinyInteger('reminder_interval_days')->default(5);
        });
        Schema::table('mf_billing_auto_outbox', function (Blueprint $table): void {
            $table->unsignedTinyInteger('template_index')->nullable(); // 0, 1 ou 2
        });
    }

    public function down(): void
    {
        // Historico financeiro: NÃO executar rollback em produção.
        Schema::table('mf_billing_auto_outbox', function (Blueprint $table): void {
            $table->dropColumn('template_index');
        });
        Schema::table('mf_billing_accounts', function (Blueprint $table): void {
            $table->dropColumn(['max_reminders', 'reminder_interval_days']);
        });
    }
};
