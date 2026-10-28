<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class StudentAssessmentPhotoComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_matches_positions_and_orders_dates_without_public_urls(): void
    {
        $admin = $this->user(UserRole::Admin);
        $professional = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $older = $this->assessment($student, $professional, '2026-05-04');
        $newer = $this->assessment($student, $professional, '2026-09-10');
        $this->complete($older);
        $this->complete($newer);
        $this->consent($older, $professional);
        $this->consent($newer, $professional);
        $this->photo($older, $professional, 'front');
        $this->photo($newer, $professional, 'front');
        $this->photo($newer, $professional, 'side');

        Sanctum::actingAs($admin);
        $response = $this->getJson($this->url($student, $newer, $older))
            ->assertOk()
            ->assertJsonPath('data.base.uuid', $older->uuid)
            ->assertJsonPath('data.target.uuid', $newer->uuid)
            ->assertJsonPath('data.available_pairs', 1)
            ->assertJsonCount(4, 'data.positions')
            ->assertJsonPath('data.positions.0.position', 'front')
            ->assertJsonPath('data.positions.0.both_available', true)
            ->assertJsonPath('data.positions.0.base.has_photo', true)
            ->assertJsonPath('data.positions.0.target.has_photo', true)
            ->assertJsonPath('data.positions.3.position', 'side')
            ->assertJsonPath('data.positions.3.base.has_photo', false)
            ->assertJsonPath('data.positions.3.target.has_photo', true);

        $this->assertEqualsCanonicalizing(
            ['private', 'no-store'],
            array_map('trim', explode(',', (string) $response->headers->get('Cache-Control')))
        );
        $json = $response->getContent();
        $this->assertIsString($json);
        $this->assertStringNotContainsString('private-test-path', $json);
        $this->assertStringNotContainsString('photo_url', $json);
        $this->assertStringNotContainsString('base64', $json);

        $log = AuditLog::query()
            ->where('action', 'student.assessment_photo_comparison.view')
            ->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($student->id, $log->student_id);
        $this->assertSame($older->uuid, $log->metadata['base_uuid']);
        $this->assertStringNotContainsString('private-test-path', $log->getRawOriginal('metadata'));
    }

    public function test_completed_assessments_without_photos_return_empty_pairs(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $first = $this->assessment($student, $evaluator, '2026-01-01');
        $second = $this->assessment($student, $evaluator, '2026-02-01');
        $this->complete($first);
        $this->complete($second);
        $this->consent($first, $evaluator);
        $this->consent($second, $evaluator);

        Sanctum::actingAs($admin);
        $this->getJson($this->url($student, $first, $second))
            ->assertOk()
            ->assertJsonPath('data.available_pairs', 0)
            ->assertJsonPath('data.positions.0.base.has_photo', false)
            ->assertJsonPath('data.positions.0.base.observation', null);
    }

    public function test_missing_or_revoked_consent_blocks_comparison_without_logging_view(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $first = $this->assessment($student, $evaluator, '2026-01-01');
        $second = $this->assessment($student, $evaluator, '2026-07-01');
        $this->complete($first);
        $this->complete($second);
        $this->consent($first, $evaluator);

        Sanctum::actingAs($admin);
        $this->getJson($this->url($student, $first, $second))->assertForbidden();

        $this->consent($second, $evaluator);
        $second->photoConsent->update(['revoked_at' => now(), 'revoked_by' => $evaluator->id]);
        $this->getJson($this->url($student, $first, $second))->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'student.assessment_photo_comparison.view',
        ]);
    }

    public function test_storage_not_authorized_blocks_comparison(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $first = $this->assessment($student, $evaluator, '2026-01-01');
        $second = $this->assessment($student, $evaluator, '2026-03-01');
        $this->complete($first);
        $this->complete($second);
        $this->consent($first, $evaluator);
        $this->consent($second, $evaluator, false);

        Sanctum::actingAs($admin);
        $this->getJson($this->url($student, $first, $second))->assertForbidden();
    }

    public function test_photo_stream_requires_active_consent_even_after_photo_was_saved(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $assessment = $this->assessment($student, $evaluator, '2026-04-01');
        $this->complete($assessment);
        $this->consent($assessment, $evaluator);
        $this->photo($assessment, $evaluator, 'front');

        config()->set('student-photos.disk', 'student_photos_local');
        Storage::fake('student_photos_local');
        Storage::disk('student_photos_local')->put('private-test-path/front.jpg', 'jpeg-test-content');

        Sanctum::actingAs($admin);
        $photoResponse = $this->get("/api/v1/assessments/{$assessment->uuid}/progress-photos/front")
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            ['private', 'no-store'],
            array_map('trim', explode(',', (string) $photoResponse->headers->get('Cache-Control')))
        );

        $assessment->photoConsent->update(['revoked_at' => now(), 'revoked_by' => $evaluator->id]);
        $this->get("/api/v1/assessments/{$assessment->uuid}/progress-photos/front")
            ->assertForbidden();
    }

    public function test_evaluator_cannot_access_another_evaluators_photo_comparison(): void
    {
        $one = $this->user(UserRole::Evaluator);
        $two = $this->user(UserRole::Evaluator);
        $student = $this->student($one);
        $first = $this->assessment($student, $one, '2026-01-01');
        $second = $this->assessment($student, $two, '2026-03-01');
        $this->complete($first);
        $this->complete($second);
        $this->consent($first, $one);
        $this->consent($second, $two);

        Sanctum::actingAs($one);
        $this->getJson($this->url($student, $first, $second))->assertNotFound();
    }

    public function test_admin_cannot_use_a_photo_from_another_student(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $studentA = $this->student($admin);
        $studentB = $this->student($admin);
        $first = $this->assessment($studentA, $evaluator, '2026-01-01');
        $second = $this->assessment($studentB, $evaluator, '2026-04-01');
        $this->complete($first);
        $this->complete($second);
        $this->consent($first, $evaluator);
        $this->consent($second, $evaluator);

        Sanctum::actingAs($admin);
        $this->getJson($this->url($studentA, $first, $second))->assertNotFound();
    }

    public function test_reception_cannot_compare_clinical_photos(): void
    {
        $reception = $this->user(UserRole::Reception);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($reception);
        $first = $this->assessment($student, $evaluator, '2026-01-01');
        $second = $this->assessment($student, $evaluator, '2026-02-01');
        $this->complete($first);
        $this->complete($second);

        Sanctum::actingAs($reception);
        $this->getJson($this->url($student, $first, $second))->assertForbidden();
    }

    public function test_guest_is_unauthorized(): void
    {
        $admin = $this->user(UserRole::Admin);
        $student = $this->student($admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $first = $this->assessment($student, $evaluator, '2026-01-01');
        $second = $this->assessment($student, $evaluator, '2026-02-01');
        $this->getJson($this->url($student, $first, $second))->assertUnauthorized();
    }

    public function test_draft_evaluation_is_rejected(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $first = $this->assessment($student, $evaluator, '2026-01-01');
        $second = $this->assessment($student, $evaluator, '2026-02-01');
        $this->complete($first);

        Sanctum::actingAs($admin);
        $this->getJson($this->url($student, $first, $second))->assertUnprocessable();
    }

    public function test_same_uuid_or_invalid_uuid_is_rejected(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $student = $this->student($admin);
        $first = $this->assessment($student, $evaluator, '2026-01-01');

        Sanctum::actingAs($admin);
        $this->getJson($this->url($student, $first, $first))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_uuid', 'second_uuid']);
        $this->getJson("/api/v1/students/{$student->uuid}/assessment-photo-comparison?first_uuid=abc&second_uuid=def")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_uuid', 'second_uuid']);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function student(User $creator): Student
    {
        return Student::create([
            'name' => 'Aluno Fotografias',
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

    private function complete(Assessment $assessment): void
    {
        $assessment->update(['status' => 'completed', 'completed_at' => now()]);
    }

    private function consent(Assessment $assessment, User $evaluator, bool $storageAuthorized = true): void
    {
        $assessment->photoConsent()->create([
            'purpose' => 'Registro e comparação de evolução física',
            'consent_date' => $assessment->evaluation_date->format('Y-m-d'),
            'external_use_allowed' => false,
            'storage_authorized' => $storageAuthorized,
            'registered_by' => $evaluator->id,
            'updated_by' => $evaluator->id,
        ]);
    }

    private function photo(Assessment $assessment, User $evaluator, string $position): void
    {
        $assessment->progressPhotos()->create([
            'position' => $position,
            'photo_path' => 'private-test-path/'.$position.'.jpg',
            'observation' => 'Observação reservada',
            'captured_at' => $assessment->evaluation_date,
            'uploaded_at' => now(),
            'uploaded_by' => $evaluator->id,
            'updated_by' => $evaluator->id,
        ]);
    }

    private function url(Student $student, Assessment $first, Assessment $second): string
    {
        return '/api/v1/students/'.$student->uuid.'/assessment-photo-comparison'
            .'?first_uuid='.$first->uuid.'&second_uuid='.$second->uuid;
    }
}
