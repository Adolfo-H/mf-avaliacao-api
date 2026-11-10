<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MfBillingAutoOutbox;
use App\Services\MfBillingAutoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MfBillingLocalWorkerController extends Controller
{
    public function __construct(private MfBillingAutoService $automation) {}

    public function heartbeat(Request $r): JsonResponse
    {
        $data = $r->validate(['phone' => 'required|string|max:30', 'ready' => 'required|boolean']);
        try { return response()->json(['data' => $this->automation->heartbeat($data['phone'], (bool) $data['ready'])]); }
        catch (\InvalidArgumentException $e) { return response()->json(['message' => $e->getMessage()], 422); }
    }

    public function claim(Request $r): JsonResponse
    {
        $data = $r->validate(['phone' => 'required|string|max:30']);
        try { $job = $this->automation->claim($data['phone']); }
        catch (\InvalidArgumentException $e) { return response()->json(['message' => $e->getMessage()], 422); }
        return response()->json(['data' => $job]);
    }

    public function check(Request $r, MfBillingAutoOutbox $job): JsonResponse
    {
        $data = $r->validate(['phone' => 'required|string|max:30']);
        try { return response()->json(['data' => $this->automation->check($job, $data['phone'])]); }
        catch (\InvalidArgumentException $e) { return response()->json(['message' => $e->getMessage()], 422); }
    }

    public function ack(Request $r, MfBillingAutoOutbox $job): JsonResponse
    {
        $data = $r->validate([
            'status' => 'required|in:sent,unknown,not_sent',
            'provider_id' => 'nullable|string|max:255',
            'error_code' => 'nullable|string|max:80',
        ]);
        return response()->json(['data' => $this->automation->acknowledge($job, $data['status'], $data['provider_id'] ?? null, $data['error_code'] ?? null)]);
    }

    public function optOut(Request $r): JsonResponse
    {
        $data = $r->validate(['phone' => 'required|string|max:30']);
        try { return response()->json(['count' => $this->automation->optOut($data['phone'])]); }
        catch (\InvalidArgumentException $e) { return response()->json(['message' => $e->getMessage()], 422); }
    }
}
