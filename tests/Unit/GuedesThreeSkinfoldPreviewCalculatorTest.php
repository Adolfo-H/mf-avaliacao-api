<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\GuedesThreeSkinfoldPreviewCalculator;
use PHPUnit\Framework\TestCase;

final class GuedesThreeSkinfoldPreviewCalculatorTest extends TestCase
{
    private GuedesThreeSkinfoldPreviewCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new GuedesThreeSkinfoldPreviewCalculator();
    }

    public function test_male_three_sites_and_siri_are_calculated(): void
    {
        $preview = $this->calculator->evaluate('guedes_3', 'male', 25, [
            'triceps' => 20, 'suprailiac' => 25, 'abdominal' => 15,
            'chest' => 150, // Extra ignorada por este protocolo.
        ]);
        $this->assertSame('preview_available', $preview['status']);
        $this->assertSame(['triceps', 'suprailiac', 'abdominal'], $preview['required_skinfolds']);
        $this->assertSame(60.0, $preview['sum_skinfolds_mm']);
        $this->assertSame(1.05212, $preview['body_density_g_ml']);
        $this->assertSame(20.48, $preview['body_fat_percentage_preview']);
        $this->assertFalse($preview['persisted']);
        $this->assertFalse($preview['professionally_approved']);
    }

    public function test_female_uses_correct_distinct_sites(): void
    {
        $preview = $this->calculator->evaluate('guedes_3', 'female', 25, [
            'subscapular' => 12, 'suprailiac' => 16, 'thigh' => 22,
            'triceps' => 250, // Não deve entrar na soma feminina.
        ]);
        $this->assertSame(['subscapular', 'suprailiac', 'thigh'], $preview['required_skinfolds']);
        $this->assertSame(50.0, $preview['sum_skinfolds_mm']);
        $this->assertSame(1.04650, $preview['body_density_g_ml']);
        $this->assertSame(23.0, $preview['body_fat_percentage_preview']);
    }

    public function test_without_all_required_fields_never_invents_fat_percentage(): void
    {
        $preview = $this->calculator->evaluate('guedes_3', 'male', 23, ['triceps' => 12]);
        $this->assertSame('missing_measurements', $preview['status']);
        $this->assertSame(['suprailiac', 'abdominal'], $preview['missing_skinfolds']);
        $this->assertNull($preview['body_fat_percentage_preview']);
    }

    public function test_missing_or_invalid_values_are_rejected(): void
    {
        foreach ([0, -1, 'abc', INF, 1000] as $value) {
            $preview = $this->calculator->evaluate('guedes_3', 'male', 23, [
                'triceps' => $value,
                'suprailiac' => 12,
                'abdominal' => 15,
            ]);
            $this->assertSame('invalid_measurements', $preview['status']);
            $this->assertNull($preview['body_density_g_ml']);
        }
    }

    public function test_age_and_reference_restrictions_are_respected(): void
    {
        $folds = ['triceps' => 12, 'suprailiac' => 13, 'abdominal' => 14];
        $this->assertSame('missing_age', $this->calculator->evaluate('guedes_3', 'male', null, $folds)['status']);
        $this->assertSame('outside_studied_age_range', $this->calculator->evaluate('guedes_3', 'male', 28, $folds)['status']);
        $this->assertSame('outside_studied_age_range', $this->calculator->evaluate('guedes_3', 'female', 30, $folds)['status']);
        $this->assertSame('unsupported_sex_for_equation', $this->calculator->evaluate('guedes_3', 'other', 23, $folds)['status']);
        $this->assertSame('protocol_not_selected', $this->calculator->evaluate('pollock_7', 'male', 23, $folds)['status']);
    }
}
