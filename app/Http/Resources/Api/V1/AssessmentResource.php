<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentSectionType;
use App\Enums\AssessmentStatus;
use App\Models\AssessmentSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        $status =
            $this->status;

        $sections =
            $this->sections
                ->sortBy(
                    function (
                        AssessmentSection $section
                    ): int {
                        return $section
                            ->section
                            ->order();
                    }
                )
                ->values();

        $completedSections =
            $sections->filter(
                function (
                    AssessmentSection $section
                ): bool {
                    return $section->status ===
                        AssessmentSectionStatus::Completed;
                }
            )->count();

        $totalSections =
            $sections->count();

        $progressPercentage =
            $totalSections > 0
                ? (int) round(
                    (
                        $completedSections /
                        $totalSections
                    ) * 100
                )
                : 0;

        return [
            'uuid' => $this->uuid,

            'evaluation_date' => $this
                ->evaluation_date
                ?->format(
                    'Y-m-d'
                ),

            'status' => $status instanceof AssessmentStatus
                    ? $status->value
                    : (string) $status,

            'status_label' => $status instanceof AssessmentStatus
                    ? $status->label()
                    : null,

            'completed_at' => $this
                ->completed_at
                ?->toIso8601String(),

            'can_edit' => $this->isDraft(),

            'student' => [
                'uuid' => $this
                    ->student
                    ?->uuid,

                'name' => $this
                    ->student
                    ?->name,

                'age_at_evaluation' => $this
                    ->ageAtEvaluation(),

                'current_age' => $this
                    ->student
                    ?->currentAge(),

                'has_photo' => (bool) $this
                    ->student
                    ?->photo_path,

                'active' => (bool) $this
                    ->student
                    ?->active,
            ],

            'evaluator' => [
                'id' => $this
                    ->evaluator
                    ?->id,

                'name' => $this
                    ->evaluator
                    ?->name,

                'email' => $this
                    ->evaluator
                    ?->email,

                'active' => (bool) $this
                    ->evaluator
                    ?->active,
            ],

            'sections' => $sections->map(
                function (
                    AssessmentSection $section
                ): array {
                    $type =
                        $section
                            ->section;

                    $sectionStatus =
                        $section
                            ->status;

                    return [
                        'key' => $type instanceof AssessmentSectionType
                                ? $type->value
                                : (string) $type,

                        'label' => $type instanceof AssessmentSectionType
                                ? $type->label()
                                : null,

                        'order' => $type instanceof AssessmentSectionType
                                ? $type->order()
                                : null,

                        'status' => $sectionStatus instanceof AssessmentSectionStatus
                                ? $sectionStatus->value
                                : (string) $sectionStatus,

                        'status_label' => $sectionStatus instanceof AssessmentSectionStatus
                                ? $sectionStatus->label()
                                : null,

                        'started_at' => $section
                            ->started_at
                            ?->toIso8601String(),

                        'completed_at' => $section
                            ->completed_at
                            ?->toIso8601String(),
                    ];
                }
            )
                ->values(),

            'progress' => [
            'completed' => $completedSections,

            'total' => $totalSections,

            'percentage' => $progressPercentage,
            ],

            'created_by' => [
            'id' => $this
                ->createdBy
                ?->id,

            'name' => $this
                ->createdBy
                ?->name,
            ],

            'updated_by' => $this->updatedBy
                    ? [
                    'id' => $this
                        ->updatedBy
                        ->id,

                    'name' => $this
                        ->updatedBy
                        ->name,
                ]
                    : null,

            'created_at' => $this
                ->created_at
                ?->toIso8601String(),

            'updated_at' => $this
                ->updated_at
                ?->toIso8601String(),
        ];
    }
}
