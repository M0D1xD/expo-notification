<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Support;

use Illuminate\Database\Eloquent\Model;
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;

class Display
{
    /** The owner's title attribute ("name" by default), "Guest" for a device nobody is signed in on. */
    public static function owner(?Model $owner): string
    {
        if ($owner === null) {
            return __('expo-notification::filament.owner.guest');
        }

        return (string) ($owner->getAttribute(ExpoNotificationPlugin::get()->getOwnerTitleAttribute())
            ?? class_basename($owner).' #'.$owner->getKey());
    }

    public static function rate(?float $rate): string
    {
        return $rate === null ? '—' : round($rate * 100).'%';
    }
}
