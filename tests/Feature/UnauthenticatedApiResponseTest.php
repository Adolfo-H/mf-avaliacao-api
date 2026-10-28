<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UnauthenticatedApiResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_receives_401_for_api_without_json_accept_header(): void
    {
        $this->get('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_guest_receives_401_for_pdf_accept_header_instead_of_500(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Admin, 'active' => true]);
        $evaluator = User::factory()->create(['role' => UserRole::Evaluator, 'active' => true]);
        $student = Student::create([
            'name' => 'Aluno Protegido',
            'active' => true,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
        $assessment = Assessment::create([
            'student_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'evaluation_date' => '2026-10-10',
            'status' => 'completed',
            'completed_at' => now(),
            'created_by' => $evaluator->id,
            'updated_by' => $evaluator->id,
        ]);

        $this->withHeader('Accept', 'application/pdf')
            ->get("/api/v1/students/{$student->uuid}/assessment-reports/{$assessment->uuid}/pdf")
            ->assertUnauthorized();
    }
}
