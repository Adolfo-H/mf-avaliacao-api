<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentAssessmentEvolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-28 14:00:00'));
    }

    public function test_admin_receives_chronological_observations_and_saved_results(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);

        $old = $this->assessment($student, $evaluator, '2026-05-10');
        $new = $this->assessment($student, $evaluator, '2026-10-12');
        $this->body($old, 80.5, 1.75, 26.29);
        $this->body($new, 78.25, 1.75, 25.55);
        $this->anthropometry($old, 83.6, 99.4, 0.8410, 27.3);
        $this->anthropometry($new, 80.2, 98.1, 0.8175, 26.4);

        Sanctum::actingAs($admin);
        $response = $this->getJson($this->url($student))
            ->assertOk()
            ->assertJsonPath('data.period_months', 6)
            ->assertJsonPath('data.assessment_count', 2)
            ->assertJsonPath('data.has_more', false)
            ->assertJsonPath('data.metrics.0.key', 'weight_kg')
            ->assertJsonPath('data.metrics.0.points.0.assessment_uuid', $old->uuid)
            ->assertJsonPath('data.metrics.0.points.1.assessment_uuid', $new->uuid)
            ->assertJsonPath('data.metrics.0.points.0.value', 80.5)
            ->assertJsonPath('data.metrics.0.points.1.value', 78.25)
            ->assertJsonPath('data.metrics.2.points.1.value', 25.55)
            ->assertJsonPath('data.metrics.3.points.1.value', 80.2)
            ->assertJsonPath('data.metrics.9.points.1.value', 26.4)
            ->assertJsonPath('data.metrics.23.key', 'waist_to_hip_ratio')
            ->assertJsonPath('data.metrics.23.points.0.value', 0.841)
            ->assertJsonPath('data.metrics.23.points.1.value', 0.8175);

        $this->assertCount(24, $response->json('data.metrics'));
        $this->assertNotContains('body_fat_percentage', array_column($response->json('data.metrics'), 'key'));

        $log = AuditLog::query()->where('action', 'student.assessment_evolution.view')->firstOrFail();
        $this->assertSame($student->id, $log->student_id);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(6, $log->metadata['period_months']);
        $this->assertStringNotContainsString('80.5', $log->getRawOriginal('metadata'));
        $this->assertStringNotContainsString('26.29', $log->getRawOriginal('metadata'));
    }

    public function test_missing_values_are_null_not_zero_or_fabricated(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $old = $this->assessment($student, $evaluator, '2026-07-10');
        $new = $this->assessment($student, $evaluator, '2026-09-10');
        $this->body($new, 72.35, 1.70, null);

        Sanctum::actingAs($admin);
        $this->getJson($this->url($student))
            ->assertOk()
            ->assertJsonPath('data.metrics.0.valid_points', 1)
            ->assertJsonPath('data.metrics.0.points.0.value', null)
            ->assertJsonPath('data.metrics.0.points.1.value', 72.35)
            ->assertJsonPath('data.metrics.2.valid_points', 0)
            ->assertJsonPath('data.metrics.2.points.1.value', null)
            ->assertJsonPath('data.metrics.23.points.0.value', null)
            ->assertJsonPath('data.metrics.23.points.1.value', null);
    }

    public function test_only_completed_evaluations_within_requested_period_are_included(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $outside = $this->assessment($student, $evaluator, '2026-03-01');
        $inside = $this->assessment($student, $evaluator, '2026-07-28');
        $draft = $this->assessment($student, $evaluator, '2026-10-01', 'draft');
        $future = $this->assessment($student, $evaluator, '2026-10-29');
        $this->body($draft, 101.5, 1.75, 33.14);
        $this->body($inside, 77.8, 1.72, 26.3);

        Sanctum::actingAs($admin);
        $this->getJson($this->url($student).'?period_months=3')
            ->assertOk()
            ->assertJsonPath('data.period_start', '2026-07-28')
            ->assertJsonPath('data.assessment_count', 1)
            ->assertJsonPath('data.metrics.0.points.0.assessment_uuid', $inside->uuid);

        $this->getJson($this->url($student).'?period_months=12')
            ->assertOk()
            ->assertJsonPath('data.assessment_count', 2)
            ->assertJsonPath('data.metrics.0.points.0.assessment_uuid', $outside->uuid)
            ->assertJsonPath('data.metrics.0.points.1.assessment_uuid', $inside->uuid);
    }

    public function test_evaluator_only_sees_assessments_assigned_to_them(): void
    {
        $one = $this->user(UserRole::Evaluator);
        $two = $this->user(UserRole::Evaluator);
        $student = $this->student($one);
        $own = $this->assessment($student, $one, '2026-08-10');
        $other = $this->assessment($student, $two, '2026-09-10');
        $this->body($other, 99.9, 1.82, 30.16);

        Sanctum::actingAs($one);
        $this->getJson($this->url($student))
            ->assertOk()
            ->assertJsonPath('data.assessment_count', 1)
            ->assertJsonPath('data.metrics.0.points.0.assessment_uuid', $own->uuid)
            ->assertJsonPath('data.metrics.0.points.0.value', null);
    }

    public function test_reception_cannot_access_evolution(): void
    {
        $reception = $this->user(UserRole::Reception);
        $student = $this->student($reception);
        Sanctum::actingAs($reception);
        $this->getJson($this->url($student))->assertForbidden();
    }

    public function test_guest_cannot_access_evolution(): void
    {
        $admin = $this->user(UserRole::Admin);
        $student = $this->student($admin);
        $this->getJson($this->url($student))->assertUnauthorized();
    }

    public function test_invalid_period_is_rejected(): void
    {
        $admin = $this->user(UserRole::Admin);
        $student = $this->student($admin);
        Sanctum::actingAs($admin);
        $this->getJson($this->url($student).'?period_months=24')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_months']);
    }

    public function test_no_completed_assessments_returns_empty_series(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $this->assessment($student, $evaluator, '2026-09-10', 'draft');
        Sanctum::actingAs($admin);
        $this->getJson($this->url($student))
            ->assertOk()
            ->assertJsonPath('data.assessment_count', 0)
            ->assertJsonPath('data.metrics.0.valid_points', 0)
            ->assertJsonCount(0, 'data.metrics.0.points');
    }

    private function url(Student $student): string
    {
        return '/api/v1/students/'.$student->uuid.'/assessment-evolution';
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function student(User $creator): Student
    {
        return Student::create([
            'name' => 'Aluno Evolução',
            'birth_date' => '1990-01-01',
            'active' => true,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
    }

    private function assessment(Student $student, User $evaluator, string $date, string $status = 'completed'): Assessment
    {
        return Assessment::create([
            'student_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'evaluation_date' => $date,
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
            'created_by' => $evaluator->id,
            'updated_by' => $evaluator->id,
        ]);
    }

    private function body(Assessment $assessment, float $weight, float $height, ?float $bmi): void
    {
        $assessment->bodyComposition()->create([
            'payload' => [
                'weight_kg' => $weight,
                'height_m' => $height,
                'protocol' => 'pollock_7',
            ],
            'results' => [
                'bmi' => $bmi === null ? null : ['value' => $bmi, 'version' => '1.0'],
                'body_fat_percentage' => ['value' => 20.5],
            ],
            'updated_by' => $assessment->evaluator_id,
        ]);
    }

    private function anthropometry(Assessment $assessment, float $waist, float $hip, float $rcq, float $forearm): void
    {
        $assessment->anthropometry()->create([
            'payload' => [
                'circumferences' => [
                    'waist' => $waist,
                    'hip' => $hip,
                    'forearm' => ['right' => $forearm],
                ],
            ],
            'results' => [
                'waist_to_hip_ratio' => ['value' => $rcq, 'version' => '1.0'],
            ],
            'updated_by' => $assessment->evaluator_id,
        ]);
    }
}
