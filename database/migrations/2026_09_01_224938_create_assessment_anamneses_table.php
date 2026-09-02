<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'assessment_anamneses',
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
                 * Todo o conteúdo clínico da
                 * anamnese será armazenado
                 * criptografado pelo model.
                 */
                $table->longText(
                    'payload'
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
            'assessment_anamneses'
        );
    }
};
