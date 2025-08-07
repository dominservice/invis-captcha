<?php
namespace Dominservice\Invisible\Console\Commands;

use Illuminate\Console\Command;
use Dominservice\Invisible\ML\ModelGenerator;

class GenerateModel extends Command
{
    /** Artisan signature */
    protected $signature = 'invis:model:generate
                            {mode=thresholds : thresholds|weights}
                            {--force : overwrite existing file}';

    protected $description = 'Automatically generates default model.json '
    . 'for Invisible-Captcha (package).';

    public function handle(): int
    {
        $cfg  = config('invis.ml_model');
        if (!$cfg['enabled']) {
            $this->error('ML scoring is disabled in config/invis.php');
            return self::FAILURE;
        }

        $path = $cfg['path'];
        $mode = $this->argument('mode');

        if (file_exists($path) && !$this->option('force')) {
            $this->warn("File already exists: $path (use --force to overwrite)");
            return self::SUCCESS;
        }

        ModelGenerator::ensure($path, $mode);
        $this->info("Created default model [$mode] →  $path");

        return self::SUCCESS;
    }
}
