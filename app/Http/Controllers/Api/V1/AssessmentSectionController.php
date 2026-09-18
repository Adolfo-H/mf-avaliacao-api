<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentSectionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentSectionController extends Controller
{
    public function complete(
        Request $request,
        Assessment $assessment,
        string $section
    ): AssessmentResource {
        if ($assessment->isCompleted()) {
            abort(
                422,
                'Avaliações concluídas não podem ser alteradas.'
            );
        }

        $assessmentSection =
            $this->resolveSection(
                $assessment,
                $section
            );

        if (
            $assessmentSection->status ===
            AssessmentSectionStatus::NotStarted
        ) {
            abort(
                422,
                'Abra e salve a seção antes de marcá-la como concluída.'
            );
        }

        DB::transaction(
            function () use (
                $assessment,
                $assessmentSection,
                $request
            ): void {
                if (
                    $assessmentSection->status !==
                    AssessmentSectionStatus::Completed
                ) {
                    $assessmentSection
                        ->changeStatus(
                            AssessmentSectionStatus::Completed,
                            $request->user()->id
                        );
                }

                $assessment->update([
                    'updated_by' =>
                        $request->user()->id,
                ]);
            }
        );

        return $this->resource(
            $assessment
        );
    }

    public function reopen(
        Request $request,
        Assessment $assessment,
        string $section
    ): AssessmentResource {
        if ($assessment->isCompleted()) {
            abort(
                422,
                'Avaliações concluídas não podem ser alteradas.'
            );
        }

        $assessmentSection =
            $this->resolveSection(
                $assessment,
                $section
            );

        if (
            $assessmentSection->status ===
            AssessmentSectionStatus::NotStarted
        ) {
            abort(
                422,
                'Esta seção ainda não foi iniciada.'
            );
        }

        DB::transaction(
            function () use (
                $assessment,
                $assessmentSection,
                $request
            ): void {
                if (
                    $assessmentSection->status !==
                    AssessmentSectionStatus::InProgress
                ) {
                    $assessmentSection
                        ->changeStatus(
                            AssessmentSectionStatus::InProgress,
                            $request->user()->id
                        );
                }

                $assessment->update([
                    'updated_by' =>
                        $request->user()->id,
                ]);
            }
        );

        return $this->resource(
            $assessment
        );
    }

    private function resolveSection(
        Assessment $assessment,
        string $section
    ): AssessmentSection {
        $type =
            AssessmentSectionType::tryFrom(
                $section
            );

        if (! $type) {
            abort(
                404,
                'Seção da avaliação inválida.'
            );
        }

        return $assessment
            ->sections()
            ->where(
                'section',
                $type->value
            )
            ->firstOrFail();
    }

    private function resource(
        Assessment $assessment
    ): AssessmentResource {
        return new AssessmentResource(
            $assessment
                ->fresh()
                ->load([
                    'student',
                    'evaluator',
                    'createdBy',
                    'updatedBy',
                    'sections',
                ])
        );
    }
}
