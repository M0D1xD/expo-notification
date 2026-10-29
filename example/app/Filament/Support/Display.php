<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Model;

class Display
{
    /** The owner's name, "Guest" for a device nobody is signed in on. */
    public static function owner(?Model $owner): string
    {
        if ($owner === null) {
            return 'Guest';
        }

        return $owner->getAttribute('name') ?? class_basename($owner).' #'.$owner->getKey();
    }

    public static function rate(?float $rate): string
    {
        return $rate === null ? '—' : round($rate * 100).'%';
    }
}
