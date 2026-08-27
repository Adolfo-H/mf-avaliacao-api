<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluator_profiles', function (Blueprint $table): void {
            $table->id();

            $table
                ->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();

            $table
                ->string('phone', 30)
                ->nullable();

            $table
                ->string('professional_registration', 100)
                ->nullable()
                ->index();

            $table
                ->string('specialty', 160)
                ->nullable();

            $table
                ->string('photo_path', 1000)
                ->nullable();

            $table
                ->string('signature_path', 1000)
                ->nullable();

            $table
                ->string('company_name', 180)
                ->nullable();

            $table
                ->string('company_logo_path', 1000)
                ->nullable();

            $table
                ->boolean('active')
                ->default(true)
                ->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluator_profiles');
    }
};
