<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->id();

            $table->uuid('uuid')
                ->unique();

            $table->foreignId('student_id')
                ->constrained('students')
                ->restrictOnDelete();

            /*
             * O avaliador é um usuário que possui
             * cadastro de avaliador.
             */
            $table->foreignId('evaluator_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('evaluation_date')
                ->index();

            $table->string('status', 30)
                ->default('draft')
                ->index();

            $table->timestamp('completed_at')
                ->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'student_id',
                'evaluation_date',
            ]);

            $table->index([
                'evaluator_id',
                'evaluation_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
