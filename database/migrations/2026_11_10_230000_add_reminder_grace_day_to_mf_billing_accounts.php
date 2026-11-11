<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mf_billing_accounts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('reminder_grace_day')->nullable();
        });
    }

    public function down(): void
    {
        // Apenas rollback em banco de testes; nao executar sobre historico real.
        Schema::table('mf_billing_accounts', function (Blueprint $table): void {
            $table->dropColumn('reminder_grace_day');
        });
    }
};
