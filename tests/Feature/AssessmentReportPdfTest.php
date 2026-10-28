<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AssessmentReportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_private_pdf_and_audit_without_clinical_values(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $assessment = $this->assessment($student, $evaluator, true);

        $assessment->bodyComposition()->create([
            'payload' => ['weight_kg' => 83.7, 'height_m' => 1.78],
            'results' => ['bmi' => ['value' => 26.42]],
            'updated_by' => $evaluator->id,
        ]);
        $assessment->anthropometry()->create([
            'payload' => ['circumferences' => ['waist' => 92.4, 'hip' => 102]],
            'results' => ['waist_to_hip_ratio' => ['value' => 0.9059]],
            'updated_by' => $evaluator->id,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->get($this->url($student, $assessment))->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertEqualsCanonicalizing(
            ['private', 'no-store'],
            array_map('trim', explode(',', (string) $response->headers->get('Cache-Control')))
        );
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        $log = AuditLog::query()->where('action', 'assessment.report_pdf.download')->firstOrFail();
        $this->assertSame($assessment->id, $log->assessment_id);
        $this->assertSame($student->id, $log->student_id);
        $this->assertFalse($log->metadata['photos_included']);
        $this->assertStringNotContainsString('83.7', (string) $log->getRawOriginal('metadata'));
    }

    public function test_responsible_evaluator_can_get_pdf(): void
    {
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($evaluator);
        $assessment = $this->assessment($student, $evaluator, true);
        Sanctum::actingAs($evaluator);
        $response = $this->get($this->url($student, $assessment))->assertOk();
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function test_evaluator_cannot_download_another_evaluators_assessment(): void
    {
        $evaluator = $this->user(UserRole::Evaluator);
        $another = $this->user(UserRole::Evaluator);
        $student = $this->student($evaluator);
        $assessment = $this->assessment($student, $another, true);
        Sanctum::actingAs($evaluator);
        $this->get($this->url($student, $assessment))->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'assessment.report_pdf.download']);
    }

    public function test_assessment_must_belong_to_selected_student(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $a = $this->student($admin);
        $b = $this->student($admin);
        $assessment = $this->assessment($b, $evaluator, true);
        Sanctum::actingAs($admin);
        $this->get($this->url($a, $assessment))->assertNotFound();
    }

    public function test_reception_cannot_download_clinical_pdf(): void
    {
        $reception = $this->user(UserRole::Reception);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($reception);
        $assessment = $this->assessment($student, $evaluator, true);
        Sanctum::actingAs($reception);
        $this->get($this->url($student, $assessment))->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_download_pdf(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $assessment = $this->assessment($student, $evaluator, true);
        $this->get($this->url($student, $assessment))->assertUnauthorized();
    }

    public function test_draft_assessment_cannot_generate_pdf(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $assessment = $this->assessment($student, $evaluator, false);
        Sanctum::actingAs($admin);
        $this->get($this->url($student, $assessment))->assertUnprocessable();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'assessment.report_pdf.download']);
    }

    private function url(Student $student, Assessment $assessment): string
    {
        return "/api/v1/students/{$student->uuid}/assessment-reports/{$assessment->uuid}/pdf";
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function student(User $creator): Student
    {
        return Student::create([
            'name' => 'Aluno Relatório',
            'birth_date' => '1990-01-01',
            'active' => true,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
    }

    private function assessment(Student $student, User $evaluator, bool $completed): Assessment
    {
        return Assessment::create([
            'student_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'evaluation_date' => '2026-09-10',
            'status' => $completed ? 'completed' : 'draft',
            'completed_at' => $completed ? now() : null,
            'created_by' => $evaluator->id,
            'updated_by' => $evaluator->id,
        ]);
    }
}
