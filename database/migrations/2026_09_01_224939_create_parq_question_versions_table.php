<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'parq_question_versions',
            function (Blueprint $table): void {
                $table->id();

                $table->uuid('uuid')
                    ->unique();

                /*
                 * Identificador estável.
                 *
                 * Exemplo futuro:
                 * cardiac_condition
                 */
                $table->string(
                    'question_key',
                    100
                );

                /*
                 * Versão da redação.
                 */
                $table->unsignedSmallInteger(
                    'version'
                );

                $table->unsignedTinyInteger(
                    'position'
                );

                $table->text(
                    'question_text'
                );

                /*
                 * Somente perguntas aprovadas
                 * poderão entrar em avaliações.
                 */
                $table->boolean(
                    'active'
                )
                    ->default(false)
                    ->index();

                $table->timestamp(
                    'approved_at'
                )
                    ->nullable();

                $table->foreignId(
                    'approved_by'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamps();

                $table->unique([
                    'question_key',
                    'version',
                ]);

                $table->index([
                    'active',
                    'position',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'parq_question_versions'
        );
    }
};
