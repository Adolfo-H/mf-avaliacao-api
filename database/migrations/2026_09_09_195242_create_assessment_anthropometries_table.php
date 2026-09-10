<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'assessment_anthropometries',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'assessment_id'
                )
                    ->unique()
                    ->constrained(
                        'assessments'
                    )
                    ->cascadeOnDelete();

                /*
                 * Perímetros e diâmetros.
                 *
                 * O conteúdo será criptografado
                 * pelo cast do model.
                 */
                $table->longText(
                    'payload'
                );

                /*
                 * Resultados calculados,
                 * incluindo RCQ.
                 */
                $table->longText(
                    'results'
                );

                $table->foreignId(
                    'updated_by'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'assessment_anthropometries'
        );
    }
};
