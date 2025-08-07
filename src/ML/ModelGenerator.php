<?php

namespace Dominservice\Invisible\ML;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ModelGenerator
{
    /**
     * Generates default model and saves it to $path.
     *  - if file already exists => does nothing.
     *  - returns true when file was created.
     */
    public static function ensure(string $path, string $mode = 'thresholds'): bool
    {
        if (File::exists($path)) {
            return false;
        }

        File::ensureDirectoryExists(dirname($path));

        $json = $mode === 'weights'
            ? self::defaultWeights()
            : self::defaultThresholds();

        File::put($path, json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        Log::info('Invisible-Captcha: auto-generated model.json at ' . $path);

        return true;
    }

    /* ===== DEFAULT MODEL - WEIGHTS VERSION =============================== */
    private static function defaultWeights(): array
    {
        // Heuristic ≈ sigmoid( 1.0 + Σ w·x )
        return [
            'intercept' => 1.0,
            'weights'   => [
                'wd'  => -3.0,   // navigator.webdriver = 1  → strong negative
                'mm'  =>  0.05,  // every 20 movements ~ +1
                'kb'  =>  0.08,
                'cpu' =>  0.10,
                'dpr' =>  0.10,
            ],
        ];
    }

    /* ===== DEFAULT MODEL - THRESHOLDS VERSION ============================ */
    private static function defaultThresholds(): array
    {
        return [
            'intercept'  => 1.0,
            'thresholds' => [
                'wd'  => ['==' => 1, 'penalty' => -0.4],
                'mm'  => ['<'  => 3, 'penalty' => -0.2],
                'kb'  => ['<'  => 1, 'penalty' => -0.2],
            ],
        ];
    }
}
