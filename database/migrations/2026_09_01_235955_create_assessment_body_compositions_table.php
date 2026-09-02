<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'assessment_body_compositions',
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
                 * Entradas clínicas estruturadas.
                 * O model fará a criptografia.
                 */
                $table->longText(
                    'payload'
                );

                /*
                 * Resultados calculados e
                 * respectivas versões.
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
            'assessment_body_compositions'
        );
    }
};
