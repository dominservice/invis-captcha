<?php
/**
 *  Vendor\Invisible\ML\Scorer
 *
 *  ▸ Handles THREE scoring scenarios in one class:
 *      ①  model "linear-weights"  → σ(w·x+b)
 *      ②  model "thresholds"      → intercept ± penalties
 *      ③  no model                → legacy heuristic
 *
 *  ▸ Is 100% deterministic (no RNG).
 *  ▸ Model loaded from disk only once per process (static cache).
 */
namespace Vendor\Invisible\ML;

use InvalidArgumentException;

class Scorer
{
    /** @var array<mixed>  raw JSON model */
    private array  $model = [];

    /** @var 'weights'|'thresholds'|'heuristic' */
    private string $mode  = 'heuristic';

    /* ========== PUBLIC API ============================================== */

    /**
     * The only way to create an instance - automatic cache.
     */
    public static function load(string $path): self
    {
        static $cache = [];

        if (!isset($cache[$path])) {
            if (!is_file($path)) {
                // no file ⇒ heuristic mode
                $cache[$path] = (new self)->useHeuristic();
            } else {
                $json = json_decode(
                    file_get_contents($path),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
                $cache[$path] = (new self)->useModel($json);
            }
        }
        return $cache[$path];
    }

    /**
     * Returns probability (0 = bot, 1 = human).
     */
    public function predict(array $signals): float
    {
        return match ($this->mode) {
            'weights'    => $this->predictWeights($signals),
            'thresholds' => $this->predictThresholds($signals),
            default      => $this->predictHeuristic($signals),
        };
    }

    /* ========== PRIVATE MODEL SECTION ==================================== */

    /**
     * @param array<mixed> $json
     */
    private function useModel(array $json): self
    {
        /* 1) WEIGHTS model  -------------------------------------------------- */
        if (
            isset($json['intercept'], $json['weights'])
            && is_array($json['weights'])
        ) {
            $this->mode  = 'weights';
            $this->model = [
                'intercept' => (float) $json['intercept'],
                'weights'   => array_map('floatval', $json['weights']),
            ];
            return $this;
        }

        /* 2) THRESHOLDS model  ---------------------------------------------- */
        if (
            isset($json['intercept'], $json['thresholds'])
            && is_array($json['thresholds'])
        ) {
            // minimal validation of thresholds structure
            foreach ($json['thresholds'] as $feat => $rule) {
                if (!is_array($rule) || !isset($rule['penalty'])) {
                    throw new InvalidArgumentException(
                        "Invisible-Captcha: thresholds for '$feat' malformed"
                    );
                }
            }
            $this->mode  = 'thresholds';
            $this->model = $json;
            return $this;
        }

        throw new InvalidArgumentException(
            'Invisible-Captcha: model.json must contain either '
            . '{"intercept","weights"} or {"intercept","thresholds"}'
        );
    }

    private function useHeuristic(): self
    {
        $this->mode  = 'heuristic';
        $this->model = [];        // nothing needed
        return $this;
    }

    /* ========== WEIGHTS PATH  ============================================ */

    private function predictWeights(array $s): float
    {
        $sum = $this->model['intercept'];
        foreach ($this->model['weights'] as $feat => $w) {
            $sum += ($s[$feat] ?? 0) * $w;
        }
        // logistic sigmoid - result naturally fits in 0-1
        return 1.0 / (1.0 + exp(-$sum));
    }

    /* ========== THRESHOLDS PATH  ========================================= */

    private function predictThresholds(array $s): float
    {
        $score = (float) $this->model['intercept'];

        foreach ($this->model['thresholds'] as $feat => $rule) {
            $value   = $s[$feat] ?? null;
            $penalty = (float) $rule['penalty'];

            // rule can be written in two styles:
            // { "op":"<", "value":3, "penalty":-0.2 }
            // { "<":3, "penalty":-0.2 }    // as in README
            if (isset($rule['op'], $rule['value'])) {
                if (self::compare($value, $rule['op'], $rule['value'])) {
                    $score += $penalty;
                }
            } else {
                foreach (['<','<=','>','>=','==','!='] as $op) {
                    if (array_key_exists($op, $rule)) {
                        if (self::compare($value, $op, $rule[$op])) {
                            $score += $penalty;
                        }
                        break;
                    }
                }
            }
        }
        return self::clamp01($score);
    }

    /* ========== LEGACY PATH  ========================================= */

    private function predictHeuristic(array $s): float
    {
        $score = 1.0;
        if (!empty($s['wd']))         $score -= 0.4;            // webdriver
        if (($s['mm'] ?? 0) < 3)      $score -= 0.2;            // few mouse movements
        if (($s['kb'] ?? 0) < 1)      $score -= 0.2;            // no keyboard
        return self::clamp01($score);
    }

    /* ========== HELPERS  ================================================= */

    private static function clamp01(float $x): float
    {
        return max(0.0, min(1.0, $x));
    }

    /**
     *  @param mixed  $val
     *  @param string $op   one of <,<=,>,>=,==,!=
     *  @param mixed  $target
     */
    private static function compare($val, string $op, $target): bool
    {
        switch ($op) {
            case '<'  : return $val <  $target;
            case '<=' : return $val <= $target;
            case '>'  : return $val >  $target;
            case '>=' : return $val >= $target;
            case '==' : return $val == $target;
            case '!=' : return $val != $target;
            default   : throw new InvalidArgumentException("Bad op '$op'");
        }
    }
}