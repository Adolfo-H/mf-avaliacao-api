<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mf_billing_auto_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('run_id', 32)->unique();
            $table->string('status', 16); // started, succeeded, failed
            $table->unsignedTinyInteger('sent_count')->nullable();
            $table->string('error_code', 40)->nullable(); // somente classe de erro; sem dados pessoais
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        // Nao executar rollback em producao: o historico e necessario para o painel.
        Schema::dropIfExists('mf_billing_auto_runs');
    }
};
