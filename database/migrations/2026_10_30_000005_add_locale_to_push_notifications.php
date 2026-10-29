<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('expo-notification.tables');

        // Localized sends: every language's title and body, and the language each device got.
        Schema::table($tables['notifications'], function (Blueprint $table) {
            $table->json('translations')->nullable()->after('payload');
        });

        Schema::table($tables['deliveries'], function (Blueprint $table) {
            $table->string('locale', 35)->nullable()->after('push_token');
        });
    }

    public function down(): void
    {
        $tables = config('expo-notification.tables');

        Schema::table($tables['deliveries'], function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table($tables['notifications'], function (Blueprint $table) {
            $table->dropColumn('translations');
        });
    }
};
