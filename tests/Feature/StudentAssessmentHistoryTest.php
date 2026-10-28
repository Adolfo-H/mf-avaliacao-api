<?php

namespace Tests\Feature;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentSectionType;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentAssessmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_history_ordered_with_saved_clinical_summary(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $older = $this->assessment($student, $evaluator, '2026-08-11');
        $newer = $this->assessment($student, $evaluator, '2026-10-06');
        $newer->bodyComposition()->create([
            'payload' => [
                'weight_kg' => 80.5,
                'height_m' => 1.75,
                'protocol' => 'pollock_7',
            ],
            'results' => [
                'bmi' => ['value' => 26.29, 'version' => '1.0'],
                'body_fat_percentage' => null,
            ],
            'updated_by' => $evaluator->id,
        ]);
        $newer->sections()->where('section', AssessmentSectionType::Anamnesis->value)
            ->firstOrFail()->changeStatus(AssessmentSectionStatus::Completed, $evaluator->id);

        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/students/{$student->uuid}/assessment-history")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.uuid', $newer->uuid)
            ->assertJsonPath('data.0.weight_kg', 80.5)
            ->assertJsonPath('data.0.height_m', 1.75)
            ->assertJsonPath('data.0.bmi', 26.29)
            ->assertJsonPath('data.0.protocol', 'pollock_7')
            ->assertJsonPath('data.0.body_fat_percentage', null)
            ->assertJsonPath('data.0.progress.completed', 1)
            ->assertJsonPath('data.0.progress.total', 7)
            ->assertJsonPath('data.1.uuid', $older->uuid)
            ->assertJsonPath('meta.total', 2);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student.assessment_history.view',
            'user_id' => $admin->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_evaluator_can_only_see_own_assessments(): void
    {
        $one = $this->user(UserRole::Evaluator);
        $two = $this->user(UserRole::Evaluator);
        $student = $this->student($one);
        $own = $this->assessment($student, $one, '2026-08-11');
        $this->assessment($student, $two, '2026-10-06');

        Sanctum::actingAs($one);
        $this->getJson("/api/v1/students/{$student->uuid}/assessment-history")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $own->uuid)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_reception_cannot_read_clinical_history(): void
    {
        $reception = $this->user(UserRole::Reception);
        $student = $this->student($reception);
        Sanctum::actingAs($reception);
        $this->getJson("/api/v1/students/{$student->uuid}/assessment-history")
            ->assertForbidden();
    }

    public function test_guest_cannot_read_clinical_history(): void
    {
        $admin = $this->user(UserRole::Admin);
        $student = $this->student($admin);
        $this->getJson("/api/v1/students/{$student->uuid}/assessment-history")
            ->assertUnauthorized();
    }

    public function test_status_filter_and_pagination_are_applied(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $first = $this->assessment($student, $evaluator, '2026-08-11');
        $second = $this->assessment($student, $evaluator, '2026-09-11');
        $third = $this->assessment($student, $evaluator, '2026-10-11');
        $first->update(['status' => 'completed', 'completed_at' => now()]);
        $second->update(['status' => 'completed', 'completed_at' => now()]);

        Sanctum::actingAs($admin);
        $url = "/api/v1/students/{$student->uuid}/assessment-history?status=completed&per_page=1&page=2";
        $this->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $first->uuid)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_empty_history_returns_empty_data_and_nulls_are_not_zeroes(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/students/{$student->uuid}/assessment-history")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        $this->assessment($student, $evaluator, '2026-10-06');
        $this->getJson("/api/v1/students/{$student->uuid}/assessment-history")
            ->assertOk()
            ->assertJsonPath('data.0.weight_kg', null)
            ->assertJsonPath('data.0.bmi', null)
            ->assertJsonPath('data.0.body_fat_percentage', null)
            ->assertJsonPath('data.0.protocol', null);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $admin = $this->user(UserRole::Admin);
        $student = $this->student($admin);
        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/students/{$student->uuid}/assessment-history?per_page=100&status=not_a_status")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'status']);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function student(User $creator): Student
    {
        return Student::create([
            'name' => 'Aluno Histórico',
            'birth_date' => '1990-01-01',
            'active' => true,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
    }

    private function assessment(Student $student, User $evaluator, string $date): Assessment
    {
        return Assessment::create([
            'student_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'evaluation_date' => $date,
            'status' => 'draft',
            'created_by' => $evaluator->id,
            'updated_by' => $evaluator->id,
        ]);
    }
}
