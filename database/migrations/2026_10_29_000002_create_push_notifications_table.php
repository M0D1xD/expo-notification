<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('expo-notification.tables.notifications'), function (Blueprint $table) {
            // Sent to devices as `data.notificationId`, so their events can be matched back.
            $table->ulid('id')->primary();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            // The full message as sent to the Expo Push Service.
            $table->json('payload');
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('expo-notification.tables.notifications'));
    }
};
