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
        $randomLength = config('invis.dynamic_fields.length');
        
        // Direct lookup first
        $result = array_search($submitted, $map, true);
        if ($result !== false) {
            return $result;
        }
        
        // Extract the base name from the submitted field
        $pattern = '/^(.+)_[a-zA-Z0-9]{'.$randomLength.'}$/';
        $extractedOriginal = null;
        
        if (preg_match($pattern, $submitted, $matches)) {
            $extractedOriginal = $matches[1];
            
            // If not found directly, check if the extracted original matches any key in the map
            foreach ($map as $original => $dynamic) {
                if ($extractedOriginal === $original) {
                    return $original;
                }
            }
            
            // If still not found, check against config prefixes
            $prefixes = config('invis.dynamic_fields.prefixes', []);
            if (in_array($extractedOriginal, $prefixes)) {
                // Save this mapping for future use
                $map[$extractedOriginal] = $submitted;
                session()->put('_invis_field_map', $map);
                
                return $extractedOriginal;
            }
        }
        
        return null;
    }
}
