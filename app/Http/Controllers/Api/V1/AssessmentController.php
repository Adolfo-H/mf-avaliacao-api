<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssessmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAssessmentRequest;
use App\Http\Requests\Api\V1\UpdateAssessmentRequest;
use App\Http\Resources\Api\V1\AssessmentResource;
use App\Models\Assessment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssessmentController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],

            'student_uuid' => [
                'nullable',
                'uuid',
            ],

            'evaluator_id' => [
                'nullable',
                'integer',
            ],

            'status' => [
                'nullable',
                Rule::enum(
                    AssessmentStatus::class
                ),
            ],

            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $search = trim(
            (string) (
                $validated['search']
                ?? ''
            )
        );

        $assessments =
            Assessment::query()
                ->with([
                    'student',
                    'evaluator',
                    'createdBy',
                    'updatedBy',
                ])
                ->when(
                    $search !== '',
                    function ($query) use ($search): void {
                        $query->whereHas(
                            'student',
                            function ($query) use ($search): void {
                                $query->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                );
                            }
                        );
                    }
                )
                ->when(
                    ! empty(
                        $validated[
                            'student_uuid'
                        ]
                    ),
                    function ($query) use ($validated): void {
                        $studentUuid =
                            $validated[
                                'student_uuid'
                            ];

                        $query->whereHas(
                            'student',
                            function ($query) use ($studentUuid): void {
                                $query->where(
                                    'uuid',
                                    $studentUuid
                                );
                            }
                        );
                    }
                )
                ->when(
                    isset(
                        $validated[
                            'evaluator_id'
                        ]
                    ),
                    fn ($query) => $query->where(
                        'evaluator_id',
                        $validated[
                            'evaluator_id'
                        ]
                    )
                )
                ->when(
                    ! empty(
                        $validated[
                            'status'
                        ]
                    ),
                    fn ($query) => $query->where(
                        'status',
                        $validated[
                            'status'
                        ]
                    )
                )
                ->when(
                    ! empty(
                        $validated[
                            'date_from'
                        ]
                    ),
                    fn ($query) => $query->whereDate(
                        'evaluation_date',
                        '>=',
                        $validated[
                            'date_from'
                        ]
                    )
                )
                ->when(
                    ! empty(
                        $validated[
                            'date_to'
                        ]
                    ),
                    fn ($query) => $query->whereDate(
                        'evaluation_date',
                        '<=',
                        $validated[
                            'date_to'
                        ]
                    )
                )
                ->orderByDesc(
                    'evaluation_date'
                )
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();

        return AssessmentResource::collection(
            $assessments
        );
    }

    public function store(
        StoreAssessmentRequest $request
    ): AssessmentResource {
        $data =
            $request->validated();

        $student =
            Student::query()
                ->where(
                    'uuid',
                    $data[
                        'student_uuid'
                    ]
                )
                ->firstOrFail();

        $assessment =
            DB::transaction(
                function () use (
                    $data,
                    $student,
                    $request
                ): Assessment {
                    return Assessment::create([
                        'student_id' => $student->id,

                        'evaluator_id' => $data[
                                'evaluator_id'
                            ],

                        'evaluation_date' => $data[
                                'evaluation_date'
                            ],

                        'status' => AssessmentStatus::Draft,

                        'completed_at' => null,

                        'created_by' => $request
                            ->user()
                            ->id,

                        'updated_by' => $request
                            ->user()
                            ->id,
                    ]);
                }
            );

        return new AssessmentResource(
            $assessment->load([
                'student',
                'evaluator',
                'createdBy',
                'updatedBy',
            ])
        );
    }

    public function show(
        Assessment $assessment
    ): AssessmentResource {
        return new AssessmentResource(
            $assessment->load([
                'student',
                'evaluator',
                'createdBy',
                'updatedBy',
            ])
        );
    }

    public function update(
        UpdateAssessmentRequest $request,
        Assessment $assessment
    ): AssessmentResource {
        if ($assessment->isCompleted()) {
            abort(
                422,
                'Avaliações concluídas não podem ser alteradas.'
            );
        }

        $data =
            $request->validated();

        DB::transaction(
            function () use (
                $data,
                $request,
                $assessment
            ): void {
                $payload = [];

                if (
                    array_key_exists(
                        'student_uuid',
                        $data
                    )
                ) {
                    $student =
                        Student::query()
                            ->where(
                                'uuid',
                                $data[
                                    'student_uuid'
                                ]
                            )
                            ->firstOrFail();

                    $payload[
                        'student_id'
                    ] = $student->id;
                }

                if (
                    array_key_exists(
                        'evaluator_id',
                        $data
                    )
                ) {
                    $payload[
                        'evaluator_id'
                    ] =
                        $data[
                            'evaluator_id'
                        ];
                }

                if (
                    array_key_exists(
                        'evaluation_date',
                        $data
                    )
                ) {
                    $payload[
                        'evaluation_date'
                    ] =
                        $data[
                            'evaluation_date'
                        ];
                }

                $payload[
                    'updated_by'
                ] =
                    $request
                        ->user()
                        ->id;

                $assessment->update(
                    $payload
                );
            }
        );

        return new AssessmentResource(
            $assessment
                ->fresh()
                ->load([
                    'student',
                    'evaluator',
                    'createdBy',
                    'updatedBy',
                ])
        );
    }

    public function complete(
        Request $request,
        Assessment $assessment
    ): AssessmentResource {
        if ($assessment->isCompleted()) {
            return new AssessmentResource(
                $assessment->load([
                    'student',
                    'evaluator',
                    'createdBy',
                    'updatedBy',
                ])
            );
        }

        DB::transaction(
            function () use (
                $assessment,
                $request
            ): void {
                $assessment->update([
                    'status' => AssessmentStatus::Completed,

                    'completed_at' => now(),

                    'updated_by' => $request
                        ->user()
                        ->id,
                ]);
            }
        );

        return new AssessmentResource(
            $assessment
                ->fresh()
                ->load([
                    'student',
                    'evaluator',
                    'createdBy',
                    'updatedBy',
                ])
        );
    }
}
