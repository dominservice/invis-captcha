<?php
namespace Dominservice\Invisible\Helpers;
use Illuminate\Support\Str;

class DynamicFields
{
    public static function map(string $key): string
    {
        $map = session('_invis_field_map', []);
        if (!isset($map[$key])) {
            $map[$key] = $key.'_'.Str::random(config('invis.dynamic_fields.length'));
            session()->put('_invis_field_map', $map);
        }
        return $map[$key];
    }

    public static function original(string $submitted): ?string
    {
        $map = session('_invis_field_map', []);
        return array_search($submitted, $map, true) ?: null;
    }
}
