<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MfBillingAutoService;
use App\Services\MfBillingRunStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class MfBillingAutomationController extends Controller
{
    public function __construct(private MfBillingAutoService $automation, private MfBillingRunStatusService $runs) {}

    private function schemaReady(): void
    {
        abort_unless(Schema::hasTable('mf_billing_auto_settings') && Schema::hasTable('mf_billing_auto_outbox'), 503, 'Migração de lembretes automáticos ainda não aplicada.');
    }

    public function settings(): JsonResponse
    {
        $this->schemaReady();
        $settings = $this->automation->settings();
        return response()->json(['data' => array_merge(
            $this->automation->summary($settings),
            ['operation' => $this->runs->summary($settings)]
        )]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->schemaReady();
        $data = $request->validate([
            'revision' => 'required|integer|min:1',
            'enabled' => 'required|boolean',
            'sender_phone' => 'nullable|string|max:30',
            'first_after_days' => 'required|integer|min:1|max:30',
            'repeat_interval_days' => 'required|integer|min:2|max:30',
            'max_reminders' => 'required|integer|min:1|max:3',
            'send_time' => ['required', 'regex:/^(0[7-9]|1[0-8]):[0-5]\d$/D'],
            'templates' => 'required|array|size:3',
            'templates.*' => 'required|string|min:30|max:950',
        ]);
        try {
            $s = $this->automation->configure($data, $request->user());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['templates' => $e->getMessage()]);
        }
        return response()->json(['data' => array_merge(
            $this->automation->summary($s),
            ['operation' => $this->runs->summary($s)]
        )]);
    }

    public function history(): JsonResponse
    {
        $this->schemaReady();
        return response()->json(['data' => $this->automation->history()]);
    }
}
