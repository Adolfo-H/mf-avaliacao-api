<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'audit_logs',
            function (
                Blueprint $table
            ): void {
                $table->id();

                $table
                    ->foreignId(
                        'user_id'
                    )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table
                    ->string(
                        'action',
                        120
                    )
                    ->index();

                $table
                    ->string(
                        'auditable_type',
                        190
                    )
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'auditable_id'
                    )
                    ->nullable();

                /*
                 * Mantemos os IDs sem FK para
                 * preservar a evidência mesmo
                 * que futuramente algum registro
                 * seja removido/arquivado.
                 */
                $table
                    ->unsignedBigInteger(
                        'assessment_id'
                    )
                    ->nullable()
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'student_id'
                    )
                    ->nullable()
                    ->index();

                $table
                    ->ipAddress(
                        'ip_address'
                    )
                    ->nullable();

                $table
                    ->text(
                        'user_agent'
                    )
                    ->nullable();

                $table
                    ->json(
                        'metadata'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'occurred_at'
                    )
                    ->index();

                $table->timestamps();

                $table->index([
                    'auditable_type',
                    'auditable_id',
                ]);

                $table->index([
                    'user_id',
                    'occurred_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'audit_logs'
        );
    }
};
