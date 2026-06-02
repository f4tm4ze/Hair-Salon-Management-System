<?php

use Illuminate\Support\Facades\Storage;

if (!function_exists('image_exists')) {
    function image_exists($path)
    {
        return $path && Storage::disk('public')->exists($path);
    }
}
