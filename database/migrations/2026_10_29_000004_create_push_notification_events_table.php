<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('expo-notification.tables');

        // Raw events reported by devices: received, opened, dismissed.
        Schema::create($tables['events'], function (Blueprint $table) use ($tables) {
            $table->id();
            $table->foreignUlid('push_notification_id')->constrained($tables['notifications'])->cascadeOnDelete();
            $table->foreignId('push_token_id')->nullable()->constrained($tables['push_tokens'])->nullOnDelete();
            $table->string('type', 16);
            // Which client code path saw it: listener, task or cold-start.
            $table->string('source', 16)->nullable();
            $table->string('app_state', 16)->nullable();
            $table->timestamp('occurred_at');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            // Devices may retry; the same event is stored once. Explicit names stay under MySQL's 64-character limit.
            $table->unique(['push_notification_id', 'push_token_id', 'type'], 'push_events_notification_token_type_unique');
            $table->index(['type', 'occurred_at'], 'push_events_type_occurred_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('expo-notification.tables.events'));
    }
};
