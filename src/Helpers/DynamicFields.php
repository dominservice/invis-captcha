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
        
        // If not found directly, try to match by pattern using the session map
        foreach ($map as $original => $dynamic) {
            // Check if the submitted field matches the pattern of original_randomstring
            // where the random string is of the configured length
            $pattern = '/^(.+)_[a-zA-Z0-9]{'.$randomLength.'}$/';
            
            if (preg_match($pattern, $submitted, $matches)) {
                $extractedOriginal = $matches[1];
                if ($extractedOriginal === $original) {
                    return $original;
                }
            }
        }
        
        // If still not found and the map is empty or very small, 
        // try to extract the original name directly from the field name
        if (count($map) < 3) {
            $pattern = '/^(.+)_[a-zA-Z0-9]{'.$randomLength.'}$/';
            if (preg_match($pattern, $submitted, $matches)) {
                $extractedOriginal = $matches[1];
                
                // Check if this is a valid field name by checking against config prefixes
                $prefixes = config('invis.dynamic_fields.prefixes', []);
                if (in_array($extractedOriginal, $prefixes)) {
                    // Save this mapping for future use
                    $map[$extractedOriginal] = $submitted;
                    session()->put('_invis_field_map', $map);
                    
                    return $extractedOriginal;
                }
            }
        }
        
        return null;
    }
}
