<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStudentRequest;
use App\Http\Requests\Api\V1\UpdateStudentRequest;
use App\Http\Requests\Api\V1\UpdateStudentStatusRequest;
use App\Http\Resources\Api\V1\StudentResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim(
            (string) $request->query('search', '')
        );

        $status = (string) $request->query(
            'status',
            'active'
        );

        $students = Student::query()
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'mobile_phone',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->when(
                $status === 'active',
                function ($query): void {
                    $query
                        ->where('active', true)
                        ->whereNull('archived_at');
                }
            )
            ->when(
                $status === 'inactive',
                function ($query): void {
                    $query
                        ->where('active', false)
                        ->whereNull('archived_at');
                }
            )
            ->when(
                $status === 'archived',
                function ($query): void {
                    $query->whereNotNull('archived_at');
                }
            )
            ->when(
                $status !== 'archived',
                function ($query): void {
                    $query->whereNull('archived_at');
                }
            )
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return StudentResource::collection($students);
    }

    public function store(
        StoreStudentRequest $request
    ): StudentResource {
        $data = $request->validated();

        $student = DB::transaction(
            function () use ($data, $request): Student {
                return Student::create([
                    'name' => trim($data['name']),

                    'birth_date' => $data['birth_date'] ?? null,

                    'sex' => $data['sex'] ?? null,

                    'address' => $data['address'] ?? null,

                    'address_number' => $data['address_number'] ?? null,

                    'address_complement' => $data['address_complement'] ?? null,

                    'neighborhood' => $data['neighborhood'] ?? null,

                    'city' => $data['city'] ?? null,

                    'state' => ! empty($data['state'])
                            ? strtoupper($data['state'])
                            : null,

                    'mobile_phone' => $data['mobile_phone'] ?? null,

                    'home_phone' => $data['home_phone'] ?? null,

                    'email' => ! empty($data['email'])
                            ? strtolower(
                                trim($data['email'])
                            )
                            : null,

                    'active' => $data['active'] ?? true,

                    'administrative_notes' => $data['administrative_notes']
                        ?? null,

                    'created_by' => $request->user()->id,

                    'updated_by' => $request->user()->id,
                ]);
            }
        );

        return new StudentResource($student);
    }

    public function show(
        Student $student
    ): StudentResource {
        return new StudentResource($student);
    }

    public function update(
        UpdateStudentRequest $request,
        Student $student
    ): StudentResource {
        $data = $request->validated();

        DB::transaction(
            function () use (
                $data,
                $request,
                $student
            ): void {
                $payload = [];

                $fields = [
                    'name',
                    'birth_date',
                    'sex',
                    'address',
                    'address_number',
                    'address_complement',
                    'neighborhood',
                    'city',
                    'mobile_phone',
                    'home_phone',
                    'administrative_notes',
                ];

                foreach ($fields as $field) {
                    if (
                        array_key_exists(
                            $field,
                            $data
                        )
                    ) {
                        $payload[$field] =
                            $data[$field];
                    }
                }

                if (
                    array_key_exists(
                        'state',
                        $data
                    )
                ) {
                    $payload['state'] =
                        ! empty($data['state'])
                            ? strtoupper(
                                $data['state']
                            )
                            : null;
                }

                if (
                    array_key_exists(
                        'email',
                        $data
                    )
                ) {
                    $payload['email'] =
                        ! empty($data['email'])
                            ? strtolower(
                                trim($data['email'])
                            )
                            : null;
                }

                $payload['updated_by'] =
                    $request->user()->id;

                $student->update($payload);
            }
        );

        return new StudentResource(
            $student->fresh()
        );
    }

    public function updateStatus(
        UpdateStudentStatusRequest $request,
        Student $student
    ): StudentResource {
        $student->update([
            'active' => $request->boolean('active'),

            'updated_by' => $request->user()->id,
        ]);

        return new StudentResource(
            $student->fresh()
        );
    }
}
