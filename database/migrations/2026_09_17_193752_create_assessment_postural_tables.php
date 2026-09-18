<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'assessment_postural_assessments',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'assessment_id'
                )
                    ->unique()
                    ->constrained('assessments')
                    ->cascadeOnDelete();

                $table->longText(
                    'payload'
                );

                $table->foreignId(
                    'updated_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();
            }
        );

        Schema::create(
            'assessment_postural_photos',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'assessment_id'
                )
                    ->constrained('assessments')
                    ->cascadeOnDelete();

                $table->string(
                    'position',
                    40
                );

                $table->string(
                    'photo_path'
                );

                $table->boolean(
                    'grid_enabled'
                )->default(false);

                /*
                 * Reservado para as configurações
                 * reais do simetrógrafo.
                 * Não estamos inventando parâmetros.
                 */
                $table->longText(
                    'grid_settings'
                )->nullable();

                $table->text(
                    'observation'
                )->nullable();

                $table->timestamp(
                    'captured_at'
                )->nullable();

                $table->timestamp(
                    'uploaded_at'
                );

                $table->foreignId(
                    'uploaded_by'
                )
                    ->constrained('users');

                $table->foreignId(
                    'updated_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->unique([
                    'assessment_id',
                    'position',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'assessment_postural_photos'
        );

        Schema::dropIfExists(
            'assessment_postural_assessments'
        );
    }
};
