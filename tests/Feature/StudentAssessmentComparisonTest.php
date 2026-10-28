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

final class StudentAssessmentComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_compares_saved_values_in_chronological_order(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);

        $old = $this->assessment($student, $evaluator, '2026-06-12');
        $new = $this->assessment($student, $evaluator, '2026-10-12');
        $this->complete($old);
        $this->complete($new);

        $this->composition($old, 80.5, 1.75, 26.29, 'pollock_7');
        $this->composition($new, 78.2, 1.75, 25.53, 'pollock_7');
        $this->anthropometry($old, 90, 101, 0.8911);
        $this->anthropometry($new, 88, 100, 0.88);

        Sanctum::actingAs($admin);

        // Intencionalmente passa a nova avaliação antes da antiga.
        $response = $this->getJson($this->url($student, $new, $old))
            ->assertOk()
            ->assertJsonPath('data.student.uuid', $student->uuid)
            ->assertJsonPath('data.base.uuid', $old->uuid)
            ->assertJsonPath('data.target.uuid', $new->uuid)
            ->assertJsonPath('data.metrics.0.key', 'weight_kg')
            ->assertJsonPath('data.metrics.0.base', 80.5)
            ->assertJsonPath('data.metrics.0.target', 78.2)
            ->assertJsonPath('data.metrics.0.delta', -2.3)
            ->assertJsonPath('data.metrics.2.key', 'bmi')
            ->assertJsonPath('data.metrics.2.delta', -0.76)
            ->assertJsonPath('data.metrics.4.key', 'waist_cm')
            ->assertJsonPath('data.metrics.4.delta', -2)
            ->assertJsonPath('data.metrics.6.key', 'waist_to_hip_ratio');

        $this->assertEquals(0.88, $response->json('data.metrics.6.target'));
        $this->assertEquals(-0.0111, $response->json('data.metrics.6.delta'));

        $log = AuditLog::query()
            ->where('action', 'student.assessment_comparison.view')
            ->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($student->id, $log->student_id);
        $this->assertSame($old->uuid, $log->metadata['base_uuid']);
        $this->assertStringNotContainsString('80.5', $log->getRawOriginal('metadata'));
    }

    public function test_missing_clinical_values_stay_null_instead_of_zero(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $old = $this->assessment($student, $evaluator, '2026-01-01');
        $new = $this->assessment($student, $evaluator, '2026-09-01');
        $this->complete($old);
        $this->complete($new);
        $this->composition($new, 76, 1.70, 26.30);

        Sanctum::actingAs($admin);
        $this->getJson($this->url($student, $old, $new))
            ->assertOk()
            ->assertJsonPath('data.metrics.0.base', null)
            ->assertJsonPath('data.metrics.0.target', 76)
            ->assertJsonPath('data.metrics.0.delta', null)
            ->assertJsonPath('data.metrics.3.base', null)
            ->assertJsonPath('data.metrics.3.target', null)
            ->assertJsonPath('data.metrics.3.delta', null);
    }

    public function test_evaluator_cannot_compare_with_another_evaluators_assessment(): void
    {
        $one = $this->user(UserRole::Evaluator);
        $two = $this->user(UserRole::Evaluator);
        $student = $this->student($one);
        $first = $this->assessment($student, $one, '2026-01-01');
        $second = $this->assessment($student, $two, '2026-08-01');
        $this->complete($first);
        $this->complete($second);

        Sanctum::actingAs($one);
        $this->getJson($this->url($student, $first, $second))
            ->assertNotFound();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'student.assessment_comparison.view',
        ]);
    }

    public function test_admin_cannot_compare_an_assessment_belonging_to_other_student(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $studentA = $this->student($admin);
        $studentB = $this->student($admin);
        $first = $this->assessment($studentA, $evaluator, '2026-01-01');
        $second = $this->assessment($studentB, $evaluator, '2026-07-01');
        $this->complete($first);
        $this->complete($second);

        Sanctum::actingAs($admin);
        $this->getJson($this->url($studentA, $first, $second))
            ->assertNotFound();
    }

    public function test_reception_is_forbidden(): void
    {
        $reception = $this->user(UserRole::Reception);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($reception);
        $one = $this->assessment($student, $evaluator, '2026-01-01');
        $two = $this->assessment($student, $evaluator, '2026-07-01');
        $this->complete($one);
        $this->complete($two);

        Sanctum::actingAs($reception);
        $this->getJson($this->url($student, $one, $two))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_is_rejected(): void
    {
        $admin = $this->user(UserRole::Admin);
        $student = $this->student($admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $one = $this->assessment($student, $evaluator, '2026-01-01');
        $two = $this->assessment($student, $evaluator, '2026-07-01');
        $this->complete($one);
        $this->complete($two);

        $this->getJson($this->url($student, $one, $two))
            ->assertUnauthorized();
    }

    public function test_draft_assessment_is_rejected(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $one = $this->assessment($student, $evaluator, '2026-01-01');
        $two = $this->assessment($student, $evaluator, '2026-07-01');
        $this->complete($one);

        Sanctum::actingAs($admin);
        $this->getJson($this->url($student, $one, $two))
            ->assertUnprocessable();
    }

    public function test_same_assessment_and_invalid_uuid_are_rejected(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $one = $this->assessment($student, $evaluator, '2026-01-01');
        $this->complete($one);

        Sanctum::actingAs($admin);
        $this->getJson($this->url($student, $one, $one))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_uuid', 'second_uuid']);

        $this->getJson("/api/v1/students/{$student->uuid}/assessment-comparison?first_uuid=abc&second_uuid=def")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_uuid', 'second_uuid']);
    }

    public function test_identical_dates_order_by_original_creation(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $one = $this->assessment($student, $evaluator, '2026-06-01');
        $two = $this->assessment($student, $evaluator, '2026-06-01');
        $this->complete($one);
        $this->complete($two);

        Sanctum::actingAs($admin);
        $this->getJson($this->url($student, $two, $one))
            ->assertOk()
            ->assertJsonPath('data.base.uuid', $one->uuid)
            ->assertJsonPath('data.target.uuid', $two->uuid);
    }

    private function url(Student $student, Assessment $first, Assessment $second): string
    {
        return '/api/v1/students/'.$student->uuid.'/assessment-comparison'
            .'?first_uuid='.$first->uuid.'&second_uuid='.$second->uuid;
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'active' => true,
        ]);
    }

    private function student(User $user): Student
    {
        return Student::create([
            'name' => 'Aluno Comparação',
            'birth_date' => '1990-01-01',
            'active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
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

    private function complete(Assessment $assessment): void
    {
        $assessment->update(['status' => 'completed', 'completed_at' => now()]);
    }

    private function composition(
        Assessment $assessment,
        float $weight,
        float $height,
        float $bmi,
        string $protocol = 'pollock_7'
    ): void {
        $assessment->bodyComposition()->create([
            'payload' => [
                'weight_kg' => $weight,
                'height_m' => $height,
                'protocol' => $protocol,
            ],
            'results' => ['bmi' => ['value' => $bmi, 'version' => '1.0']],
            'updated_by' => $assessment->evaluator_id,
        ]);
    }

    private function anthropometry(
        Assessment $assessment,
        float $waist,
        float $hip,
        float $ratio
    ): void {
        $assessment->anthropometry()->create([
            'payload' => [
                'circumferences' => [
                    'waist' => $waist,
                    'hip' => $hip,
                ],
            ],
            'results' => [
                'waist_to_hip_ratio' => [
                    'value' => $ratio,
                    'formula' => 'waist / hip',
                    'version' => '1.0',
                ],
            ],
            'updated_by' => $assessment->evaluator_id,
        ]);
    }
}
