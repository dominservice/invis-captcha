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

    /** Sztuczny przykład – zwróć 0–1 */
    public function predict(array $s): float
    {
        // TODO: implementacja prawdziwego modelu
        return 1 - (int)$s['wd']*0.4 - ($s['mm']<3?0.2:0) - ($s['kb']<1?0.2:0);
    }
}
