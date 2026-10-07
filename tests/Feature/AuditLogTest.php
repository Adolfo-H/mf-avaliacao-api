<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_logout_are_audited(): void
    {
        $user =
            User::factory()->create([
                'email' =>
                    'audit@mf.local',

                'password' =>
                    'Senha@123',

                'role' =>
                    UserRole::Evaluator,

                'active' => true,
            ]);

        $login =
            $this
                ->postJson(
                    '/api/v1/auth/login',
                    [
                        'email' =>
                            $user->email,

                        'password' =>
                            'Senha@123',

                        'device_name' =>
                            'teste-auditoria',
                    ]
                )
                ->assertOk();

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' =>
                    $user->id,

                'action' =>
                    'auth.login',

                'auditable_type' =>
                    User::class,

                'auditable_id' =>
                    $user->id,
            ]
        );

        $token =
            $login->json(
                'token'
            );

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/auth/logout'
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' =>
                    $user->id,

                'action' =>
                    'auth.logout',

                'auditable_type' =>
                    User::class,

                'auditable_id' =>
                    $user->id,
            ]
        );
    }

    public function test_viewing_assessment_is_audited(): void
    {
        $user =
            $this->createEvaluator();

        $student =
            $this->createStudent(
                $user
            );

        $assessment =
            $this->createAssessment(
                $student,
                $user
            );

        Sanctum::actingAs($user);

        $this
            ->getJson(
                "/api/v1/assessments/{$assessment->uuid}"
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' =>
                    $user->id,

                'action' =>
                    'assessment.view',

                'auditable_type' =>
                    Assessment::class,

                'auditable_id' =>
                    $assessment->id,

                'assessment_id' =>
                    $assessment->id,

                'student_id' =>
                    $student->id,
            ]
        );
    }

    public function test_photo_views_are_audited(): void
    {
        Storage::fake(
            'student_photos_local'
        );

        $user =
            $this->createEvaluator();

        $student =
            $this->createStudent(
                $user
            );

        $assessment =
            $this->createAssessment(
                $student,
                $user
            );

        Sanctum::actingAs($user);

        /*
         * Foto cadastral do aluno.
         */
        $studentPath =
            "students/{$student->uuid}/profile.jpg";

        Storage::disk(
            'student_photos_local'
        )->put(
            $studentPath,
            'fake-image'
        );

        $student->update([
            'photo_path' =>
                $studentPath,
        ]);

        $this
            ->get(
                "/api/v1/students/{$student->uuid}/photo"
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' =>
                    $user->id,

                'action' =>
                    'student.photo.view',

                'student_id' =>
                    $student->id,
            ]
        );

        /*
         * Foto de evolução.
         */
        $progressPath =
            "assessments/{$assessment->uuid}/progress/front/test.jpg";

        Storage::disk(
            'student_photos_local'
        )->put(
            $progressPath,
            'fake-image'
        );

        $progressPhoto =
            $assessment
                ->progressPhotos()
                ->create([
                    'position' =>
                        'front',

                    'photo_path' =>
                        $progressPath,

                    'uploaded_at' =>
                        now(),

                    'uploaded_by' =>
                        $user->id,

                    'updated_by' =>
                        $user->id,
                ]);

        $this
            ->get(
                "/api/v1/assessments/{$assessment->uuid}/progress-photos/front"
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' =>
                    $user->id,

                'action' =>
                    'assessment.progress_photo.view',

                'auditable_id' =>
                    $progressPhoto->id,

                'assessment_id' =>
                    $assessment->id,

                'student_id' =>
                    $student->id,
            ]
        );

        /*
         * Foto postural.
         */
        $posturalPath =
            "assessments/{$assessment->uuid}/postural/right_lateral/test.jpg";

        Storage::disk(
            'student_photos_local'
        )->put(
            $posturalPath,
            'fake-image'
        );

        $posturalPhoto =
            $assessment
                ->posturalPhotos()
                ->create([
                    'position' =>
                        'right_lateral',

                    'photo_path' =>
                        $posturalPath,

                    'grid_enabled' =>
                        false,

                    'uploaded_at' =>
                        now(),

                    'uploaded_by' =>
                        $user->id,

                    'updated_by' =>
                        $user->id,
                ]);

        $this
            ->get(
                "/api/v1/assessments/{$assessment->uuid}/postural-assessment/photos/right_lateral"
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' =>
                    $user->id,

                'action' =>
                    'assessment.postural_photo.view',

                'auditable_id' =>
                    $posturalPhoto->id,

                'assessment_id' =>
                    $assessment->id,

                'student_id' =>
                    $student->id,
            ]
        );
    }

    private function createEvaluator(): User
    {
        return User::factory()->create([
            'role' =>
                UserRole::Evaluator,

            'active' => true,
        ]);
    }

    private function createStudent(
        User $user
    ): Student {
        return Student::create([
            'name' =>
                'Aluno Teste',

            'birth_date' =>
                '1990-01-01',

            'active' => true,

            'created_by' =>
                $user->id,

            'updated_by' =>
                $user->id,
        ]);
    }

    private function createAssessment(
        Student $student,
        User $evaluator
    ): Assessment {
        return Assessment::create([
            'student_id' =>
                $student->id,

            'evaluator_id' =>
                $evaluator->id,

            'evaluation_date' =>
                '2026-10-06',

            'status' => 'draft',

            'created_by' =>
                $evaluator->id,

            'updated_by' =>
                $evaluator->id,
        ]);
    }
}
