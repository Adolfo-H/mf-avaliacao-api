<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AdultBmiReferencePreviewCalculator;
use PHPUnit\Framework\TestCase;

final class AdultBmiReferencePreviewCalculatorTest extends TestCase
{
    public function test_adult_reference_thresholds_are_exact_and_not_persisted(): void
    {
        $calculator = new AdultBmiReferencePreviewCalculator();
        foreach ([
            [18.49, 'Baixo peso'],
            [18.50, 'Faixa de referência habitual'],
            [24.99, 'Faixa de referência habitual'],
            [25.00, 'Sobrepeso'],
            [29.99, 'Sobrepeso'],
            [30.00, 'Obesidade classe I'],
            [35.00, 'Obesidade classe II'],
            [40.00, 'Obesidade classe III'],
        ] as [$bmi, $expected]) {
            $r = $calculator->evaluate(30, $bmi, 1.0);
            $this->assertSame('preview_available', $r['status']);
            $this->assertSame($expected, $r['reference_category']);
            $this->assertFalse($r['professionally_approved']);
            $this->assertFalse($r['persisted']);
        }
    }

    public function test_no_adult_reference_for_minors_or_missing_age(): void
    {
        $calculator = new AdultBmiReferencePreviewCalculator();
        $this->assertSame('outside_adult_scope', $calculator->evaluate(17, 60, 1.7)['status']);
        $this->assertNull($calculator->evaluate(17, 60, 1.7)['reference_category']);
        $this->assertSame('missing_age', $calculator->evaluate(null, 60, 1.7)['status']);
    }

    public function test_missing_invalid_or_nonfinite_inputs_do_not_generate_classification(): void
    {
        $calculator = new AdultBmiReferencePreviewCalculator();
        foreach ([[null, 1.7], [60, null], [true, 1.7], [60, 0], ['1e999', 1.7]] as [$kg, $m]) {
            $r = $calculator->evaluate(30, $kg, $m);
            $this->assertNotSame('preview_available', $r['status']);
            $this->assertNull($r['reference_category']);
        }
    }
}
