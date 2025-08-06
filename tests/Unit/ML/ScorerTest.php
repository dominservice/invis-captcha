<?php

namespace Dominservice\Invisible\Tests\Unit\ML;

use Dominservice\Invisible\ML\Scorer;
use Dominservice\Invisible\Tests\TestCase;
use Mockery;

class ScorerTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated()
    {
        $scorer = new Scorer();
        $this->assertInstanceOf(Scorer::class, $scorer);
    }

    /** @test */
    public function it_can_load_model_from_file()
    {
        // Create a temporary model file
        $modelPath = sys_get_temp_dir() . '/test_model.json';
        file_put_contents($modelPath, json_encode(['test' => 'model']));

        $scorer = Scorer::load($modelPath);
        
        $this->assertInstanceOf(Scorer::class, $scorer);
        
        // Clean up
        unlink($modelPath);
    }

    /** @test */
    public function it_can_set_model_directly()
    {
        $model = ['test' => 'model'];
        $scorer = new Scorer();
        $scorer->setModel($model);
        
        $this->assertInstanceOf(Scorer::class, $scorer);
    }

    /** @test */
    public function it_can_predict_score_based_on_input()
    {
        $scorer = new Scorer();
        
        // Test with high-risk inputs (should return low score)
        $highRiskInput = ['wd' => 1, 'mm' => 1, 'kb' => 0];
        $highRiskScore = $scorer->predict($highRiskInput);
        $this->assertLessThan(0.5, $highRiskScore);
        
        // Test with low-risk inputs (should return high score)
        $lowRiskInput = ['wd' => 0, 'mm' => 10, 'kb' => 10];
        $lowRiskScore = $scorer->predict($lowRiskInput);
        $this->assertGreaterThan(0.5, $lowRiskScore);
        
        // Verify score is between 0 and 1
        $this->assertGreaterThanOrEqual(0, $highRiskScore);
        $this->assertLessThanOrEqual(1, $highRiskScore);
        $this->assertGreaterThanOrEqual(0, $lowRiskScore);
        $this->assertLessThanOrEqual(1, $lowRiskScore);
    }
}