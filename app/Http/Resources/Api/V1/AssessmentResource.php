<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\AssessmentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status;

        return [
            'uuid' => $this->uuid,

            'evaluation_date' =>
                $this->evaluation_date?->format('Y-m-d'),

            'status' => $status instanceof AssessmentStatus
                ? $status->value
                : (string) $status,

            'status_label' => $status instanceof AssessmentStatus
                ? $status->label()
                : null,

            'completed_at' =>
                $this->completed_at?->toIso8601String(),

            'can_edit' =>
                $this->isDraft(),

            'student' => [
                'uuid' =>
                    $this->student?->uuid,

                'name' =>
                    $this->student?->name,

                'age_at_evaluation' =>
                    $this->ageAtEvaluation(),

                'current_age' =>
                    $this->student?->currentAge(),

                'has_photo' =>
                    (bool) $this->student?->photo_path,

                'active' =>
                    (bool) $this->student?->active,
            ],

            'evaluator' => [
                'id' =>
                    $this->evaluator?->id,

                'name' =>
                    $this->evaluator?->name,

                'email' =>
                    $this->evaluator?->email,

                'active' =>
                    (bool) $this->evaluator?->active,
            ],

            'created_by' => [
                'id' =>
                    $this->createdBy?->id,

                'name' =>
                    $this->createdBy?->name,
            ],

            'updated_by' => $this->updatedBy
                ? [
                    'id' =>
                        $this->updatedBy->id,

                    'name' =>
                        $this->updatedBy->name,
                ]
                : null,

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}