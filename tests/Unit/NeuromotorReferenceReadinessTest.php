<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\NeuromotorReferenceReadiness;
use PHPUnit\Framework\TestCase;

final class NeuromotorReferenceReadinessTest extends TestCase
{
    public function test_empty_payload_is_never_classified(): void
    {
        $r = (new NeuromotorReferenceReadiness())->evaluate([]);
        $this->assertSame(0, $r['recorded_count']);
        $this->assertNull($r['classification']);
        $this->assertFalse($r['professionally_approved']);
    }

    public function test_measurements_with_protocol_and_unit_are_ready_only_for_review(): void
    {
        $r = (new NeuromotorReferenceReadiness())->evaluate([
            'flexibility' => ['protocol' => 'wells_dillon', 'result' => 25.5, 'unit' => 'cm'],
            'resistance' => [
                'abdominal_flexion' => ['repetitions' => 30, 'time_seconds' => 60, 'protocol' => 'abdominais-1min'],
                'push_ups' => ['repetitions' => 12, 'time_seconds' => 60, 'protocol' => 'flexoes-1min'],
            ],
        ]);
        $this->assertSame(3, $r['recorded_count']);
        $this->assertSame('recorded_for_review', $r['items']['flexibility']['status']);
        $this->assertNull($r['classification']);
    }

    public function test_missing_units_or_execution_details_are_explicit(): void
    {
        $r = (new NeuromotorReferenceReadiness())->evaluate([
            'flexibility' => ['protocol' => 'wells_dillon', 'result' => 20],
            'resistance' => ['push_ups' => ['repetitions' => 12]],
        ]);
        $this->assertSame('missing_unit', $r['items']['flexibility']['status']);
        $this->assertSame('missing_protocol_or_duration', $r['items']['push_ups']['status']);
        $this->assertSame(0, $r['recorded_count']);
    }
}
