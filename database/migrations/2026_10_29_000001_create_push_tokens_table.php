<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $owner = config('expo-notification.owner');

        Schema::create(config('expo-notification.tables.push_tokens'), function (Blueprint $table) use ($owner) {
            $table->id();
            // Platform install ID (Android ID / iOS identifierForVendor): one row, and one push token, per device.
            $table->string('device_id')->unique();
            $table->string('push_token')->unique();
            $table->string('platform', 16);
            $table->string('locale', 35)->nullable();

            if ($owner['mode'] === 'foreign') {
                $model = new $owner['model'];
                $column = match ($owner['key_type']) {
                    'uuid' => $table->foreignUuid($owner['column']),
                    'ulid' => $table->foreignUlid($owner['column']),
                    default => $table->foreignId($owner['column']),
                };
                $column->nullable()->constrained($model->getTable(), $model->getKeyName())->nullOnDelete();
            } else {
                match ($owner['key_type']) {
                    'uuid' => $table->nullableUuidMorphs('owner'),
                    'ulid' => $table->nullableUlidMorphs('owner'),
                    default => $table->nullableMorphs('owner'),
                };
            }

            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_registered_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('expo-notification.tables.push_tokens'));
    }
};
