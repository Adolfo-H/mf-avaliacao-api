<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MfBillingRunStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MfBillingRunStatusController extends Controller
{
    public function __construct(private MfBillingRunStatusService $runs) {}

    public function record(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'run_id' => ['required', 'string', 'regex:/^[0-9]{6,24}$/D'],
            'status' => ['required', Rule::in(['started', 'succeeded', 'failed'])],
            'sent_count' => ['nullable', 'integer', 'min:0', 'max:10'],
            'error_code' => ['nullable', Rule::in([
                'preflight', 'restore', 'install', 'worker', 'backup',
                'upload', 'cleanup', 'unknown',
            ])],
        ]);
        // Sem numero, mensagem ou dados financeiros. Somente metadados da execucao.
        $run = $this->runs->record($payload);
        return response()->json(['data' => [
            'status' => $run->status,
            'run_id' => $run->run_id,
        ]]);
    }
}
