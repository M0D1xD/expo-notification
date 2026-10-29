<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('expo-notification.tables');

        // One row per notification per device: the Expo ticket and receipt, plus the per-device timeline.
        Schema::create($tables['deliveries'], function (Blueprint $table) use ($tables) {
            $table->id();
            $table->foreignUlid('push_notification_id')->constrained($tables['notifications'])->cascadeOnDelete();
            $table->foreignId('push_token_id')->nullable()->constrained($tables['push_tokens'])->nullOnDelete();
            // The token as it was when sent; the push_tokens row may rotate or be deleted later.
            $table->string('push_token');

            $table->string('ticket_id')->nullable()->index();
            $table->string('ticket_status', 16)->nullable();
            $table->string('receipt_status', 16)->nullable();
            $table->string('error_type', 64)->nullable()->index();
            $table->text('error_message')->nullable();
            $table->json('details')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('receipt_checked_at')->nullable();
            // From device events; a receipt "ok" only means FCM/APNs accepted the message.
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            // Explicit name: the generated one exceeds MySQL/MariaDB's 64-character identifier limit.
            $table->unique(['push_notification_id', 'push_token_id'], 'push_deliveries_notification_token_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('expo-notification.tables.deliveries'));
    }
};
