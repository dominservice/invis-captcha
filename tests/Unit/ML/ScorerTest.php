<?php

namespace Dominservice\Invisible\Tests\Unit\ML;

use Dominservice\Invisible\ML\Scorer;
use Dominservice\Invisible\Tests\TestCase;

class ScorerTest extends TestCase
{
    public function test_it_can_be_instantiated()
    {
        $scorer = new Scorer();

        $this->assertInstanceOf(Scorer::class, $scorer);
    }

    public function test_it_uses_heuristic_mode_when_model_file_is_missing()
    {
        $scorer = Scorer::load(sys_get_temp_dir().'/missing-invis-model.json');

        $this->assertLessThan(0.5, $scorer->predict([
            'wd' => 1,
            'mm' => 1,
            'kb' => 0,
        ]));
    }

    public function test_it_can_load_threshold_model_from_file()
    {
        $modelPath = sys_get_temp_dir().'/invis-threshold-model.json';

        file_put_contents($modelPath, json_encode([
            'intercept' => 1.0,
            'thresholds' => [
                'mm' => ['<' => 3, 'penalty' => -0.2],
                'kb' => ['<' => 1, 'penalty' => -0.2],
            ],
        ]));

        $scorer = Scorer::load($modelPath);

        $this->assertInstanceOf(Scorer::class, $scorer);
        $this->assertEqualsWithDelta(0.6, $scorer->predict([
            'mm' => 1,
            'kb' => 0,
        ]), 0.00001);

        unlink($modelPath);
    }

    public function test_it_can_predict_score_based_on_input()
    {
        $scorer = new Scorer();

        $highRiskScore = $scorer->predict(['wd' => 1, 'mm' => 1, 'kb' => 0]);
        $lowRiskScore = $scorer->predict(['wd' => 0, 'mm' => 10, 'kb' => 10]);

        $this->assertLessThan(0.5, $highRiskScore);
        $this->assertGreaterThan(0.5, $lowRiskScore);
        $this->assertGreaterThanOrEqual(0, $highRiskScore);
        $this->assertLessThanOrEqual(1, $highRiskScore);
        $this->assertGreaterThanOrEqual(0, $lowRiskScore);
        $this->assertLessThanOrEqual(1, $lowRiskScore);
    }
}
