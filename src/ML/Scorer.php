<?php
namespace Dominservice\Invisible\ML;

class Scorer
{
    protected array $model;
    public static function load(string $path): self
    {
        return (new self)->setModel(json_decode(file_get_contents($path), true));
    }
    public function setModel(array $m): self { $this->model=$m; return $this; }

    /** 
     * Predicts a score between 0-1 based on input features and model weights
     * Lower score indicates higher risk of being a bot
     */
    public function predict(array $s): float
    {
        // If no model is loaded, use default weights
        if (empty($this->model)) {
            return $this->defaultPredict($s);
        }
        
        // Initialize base score
        $score = isset($this->model['base_score']) ? $this->model['base_score'] : 1.0;
        
        // Apply feature weights from model
        foreach ($this->model['features'] ?? [] as $feature => $config) {
            if (!isset($s[$feature])) {
                continue;
            }
            
            $value = $s[$feature];
            
            // Apply threshold-based scoring
            if (isset($config['thresholds'])) {
                foreach ($config['thresholds'] as $threshold => $impact) {
                    if ($config['operator'] === '<' && $value < $threshold) {
                        $score += $impact;
                    } elseif ($config['operator'] === '>' && $value > $threshold) {
                        $score += $impact;
                    } elseif ($config['operator'] === '==' && $value == $threshold) {
                        $score += $impact;
                    }
                }
            }
            
            // Apply linear weight
            if (isset($config['weight'])) {
                $score += $value * $config['weight'];
            }
        }
        
        // Ensure score is between 0 and 1
        return max(0, min(1, $score));
    }
    
    /**
     * Default prediction logic when no model is loaded
     */
    private function defaultPredict(array $s): float
    {
        return 1 - (int)($s['wd'] ?? 0) * 0.4 - (($s['mm'] ?? 0) < 3 ? 0.2 : 0) - (($s['kb'] ?? 0) < 1 ? 0.2 : 0);
    }
}
