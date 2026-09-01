<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'assessment_sections',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'assessment_id'
                )
                    ->constrained(
                        'assessments'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'section',
                    50
                );

                $table->string(
                    'status',
                    30
                )
                    ->default(
                        'not_started'
                    )
                    ->index();

                $table->timestamp(
                    'started_at'
                )
                    ->nullable();

                $table->timestamp(
                    'completed_at'
                )
                    ->nullable();

                $table->foreignId(
                    'updated_by'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamps();

                $table->unique([
                    'assessment_id',
                    'section',
                ]);
            }
        );

        /*
         * Avaliações que já existiam antes
         * desta migration também precisam
         * receber as sete seções.
         */
        $sections = [
            'anamnesis',
            'body_composition',
            'circumferences',
            'vo2_max',
            'neuromotor_tests',
            'progress_photos',
            'postural_assessment',
        ];

        DB::table('assessments')
            ->select([
                'id',
                'created_by',
                'updated_by',
            ])
            ->orderBy('id')
            ->chunkById(
                100,
                function ($assessments) use (
                    $sections
                ): void {
                    $rows = [];

                    $now = now();

                    foreach (
                        $assessments as $assessment
                    ) {
                        foreach (
                            $sections as $section
                        ) {
                            $rows[] = [
                                'assessment_id' => $assessment->id,

                                'section' => $section,

                                'status' => 'not_started',

                                'started_at' => null,

                                'completed_at' => null,

                                'updated_by' => $assessment->updated_by
                                    ?? $assessment->created_by,

                                'created_at' => $now,

                                'updated_at' => $now,
                            ];
                        }
                    }

                    if ($rows !== []) {
                        DB::table(
                            'assessment_sections'
                        )->insert($rows);
                    }
                }
            );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'assessment_sections'
        );
    }
};
