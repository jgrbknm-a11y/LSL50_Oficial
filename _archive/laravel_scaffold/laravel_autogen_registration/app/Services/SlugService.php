<?php

namespace App\Services;

use Illuminate\Support\Str;

class SlugService
{
    /**
     * Generate a unique slug for a given Eloquent model class and column.
     */
    public static function makeUniqueSlug(string $source, string $modelClass, string $column = 'slug'): string
    {
        $base = Str::slug($source);
        $slug = $base;
        $i = 1;
        while ($modelClass::where($column, $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }
        return $slug;
    }
}
