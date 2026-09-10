<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'assessment_photo_consents',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'assessment_id'
                )
                    ->unique()
                    ->constrained('assessments')
                    ->cascadeOnDelete();

                $table->text(
                    'purpose'
                );

                $table->date(
                    'consent_date'
                );

                $table->boolean(
                    'external_use_allowed'
                )->default(false);

                $table->boolean(
                    'storage_authorized'
                )->default(false);

                $table->timestamp(
                    'revoked_at'
                )->nullable();

                $table->foreignId(
                    'registered_by'
                )
                    ->constrained('users');

                $table->foreignId(
                    'updated_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId(
                    'revoked_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();
            }
        );

        Schema::create(
            'assessment_progress_photos',
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
            'assessment_progress_photos'
        );

        Schema::dropIfExists(
            'assessment_photo_consents'
        );
    }
};
