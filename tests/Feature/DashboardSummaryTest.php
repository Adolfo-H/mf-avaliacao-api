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

final class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_real_counts_and_recent_assessments(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $active = $this->student($admin, 'Aluno Ativo', true);
        $inactive = $this->student($admin, 'Aluno Inativo', false);

        $draft = $this->assessment($active, $evaluator, '2026-10-10', 'draft');
        $completed = $this->assessment($active, $evaluator, '2026-10-11', 'completed');
        $this->assessment($inactive, $evaluator, '2026-10-12', 'completed');

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.scope', 'admin')
            ->assertJsonPath('data.students.count', 1)
            ->assertJsonPath('data.assessments.total', 3)
            ->assertJsonPath('data.assessments.draft', 1)
            ->assertJsonPath('data.assessments.completed', 2)
            ->assertJsonCount(3, 'data.recent')
            ->assertJsonPath('data.recent.1.uuid', $completed->uuid)
            ->assertJsonPath('data.recent.2.uuid', $draft->uuid);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'dashboard.summary.view',
            'user_id' => $admin->id,
        ]);
    }

    public function test_evaluator_sees_only_own_assessments_and_distinct_active_students(): void
    {
        $one = $this->user(UserRole::Evaluator);
        $two = $this->user(UserRole::Evaluator);
        $student = $this->student($one, 'Aluno do Avaliador', true);
        $other = $this->student($two, 'Aluno de Outro Avaliador', true);
        $this->assessment($student, $one, '2026-08-10', 'completed');
        $own = $this->assessment($student, $one, '2026-09-10', 'draft');
        $otherAssessment = $this->assessment($other, $two, '2026-10-10', 'completed');

        Sanctum::actingAs($one);
        $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.scope', 'evaluator')
            ->assertJsonPath('data.students.count', 1)
            ->assertJsonPath('data.assessments.total', 2)
            ->assertJsonPath('data.assessments.completed', 1)
            ->assertJsonPath('data.assessments.draft', 1)
            ->assertJsonCount(2, 'data.recent')
            ->assertJsonPath('data.recent.0.uuid', $own->uuid)
            ->assertJsonMissing(['uuid' => $otherAssessment->uuid])
            ->assertJsonMissing(['name' => $other->name]);
    }

    public function test_reception_only_sees_nonclinical_administrative_count(): void
    {
        $reception = $this->user(UserRole::Reception);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($reception, 'Aluno', true);
        $this->assessment($student, $evaluator, '2026-10-10', 'completed');

        Sanctum::actingAs($reception);
        $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.scope', 'reception')
            ->assertJsonPath('data.students.count', 1)
            ->assertJsonPath('data.assessments', null)
            ->assertJsonPath('data.permissions.can_view_assessments', false)
            ->assertJsonCount(0, 'data.recent');
    }

    public function test_guest_is_unauthorized_and_does_not_log_access(): void
    {
        $this->get('/api/v1/dashboard/summary')->assertUnauthorized();
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'dashboard.summary.view',
        ]);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function student(User $creator, string $name, bool $active): Student
    {
        return Student::create([
            'name' => $name,
            'birth_date' => '1990-01-01',
            'active' => $active,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
    }

    private function assessment(Student $student, User $evaluator, string $date, string $status): Assessment
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
}
