<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'assessment_parq_answers',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'assessment_id'
                )
                    ->constrained(
                        'assessments'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'question_version_id'
                )
                    ->constrained(
                        'parq_question_versions'
                    )
                    ->restrictOnDelete();

                /*
                 * true  = Sim
                 * false = Não
                 */
                $table->boolean(
                    'answer'
                );

                $table->foreignId(
                    'answered_by'
                )
                    ->constrained(
                        'users'
                    )
                    ->restrictOnDelete();

                $table->timestamps();

                $table->unique([
                    'assessment_id',
                    'question_version_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'assessment_parq_answers'
        );
    }
};
