<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentSectionType;
use App\Models\Assessment;

trait EnsuresAssessmentSectionIsEditable
{
    protected function ensureAssessmentSectionEditable(
        Assessment $assessment,
        AssessmentSectionType $section
    ): void {
        if ($assessment->isCompleted()) {
            abort(
                422,
                'Avaliações concluídas não podem ser alteradas.'
            );
        }

        $assessmentSection =
            $assessment
                ->sections()
                ->where(
                    'section',
                    $section->value
                )
                ->firstOrFail();

        if (
            $assessmentSection->status ===
            AssessmentSectionStatus::Completed
        ) {
            abort(
                422,
                'Seção concluída. Reabra a seção antes de alterá-la.'
            );
        }
    }
}
