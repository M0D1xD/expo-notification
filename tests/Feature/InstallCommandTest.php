<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use M0d1xd\ExpoNotification\Tests\TestCase;

final class InstallCommandTest extends TestCase
{
    private string $root;

    /** The app's config, database and .env live in a temp folder, so the command writes nothing into the repo. */
    protected function defineEnvironment($app): void
    {
        $this->root = sys_get_temp_dir().'/expo-notification-install-'.uniqid();
        (new Filesystem)->makeDirectory($this->root.'/config', 0755, true);
        (new Filesystem)->makeDirectory($this->root.'/database/migrations', 0755, true);

        $app->useConfigPath($this->root.'/config');
        $app->useDatabasePath($this->root.'/database');
        $app->useEnvironmentPath($this->root);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    private function migrations(): array
    {
        return array_map('basename', glob($this->root.'/database/migrations/*.php'));
    }

    public function test_it_publishes_the_config_and_migrations_and_adds_the_env_keys(): void
    {
        file_put_contents($this->root.'/.env', "APP_NAME=Demo\n");
        file_put_contents($this->root.'/.env.example', "APP_NAME=Laravel\n");

        $this->artisan('expo-notification:install')
            ->expectsConfirmation('Run the migrations now?', 'no')
            ->assertSuccessful();

        $this->assertFileExists($this->root.'/config/expo-notification.php');
        $this->assertCount(5, $this->migrations());
        foreach (['.env', '.env.example'] as $file) {
            $contents = file_get_contents($this->root.'/'.$file);
            $this->assertStringContainsString("\nEXPO_ACCESS_TOKEN=\n", $contents, $file);
            $this->assertStringContainsString("\nEXPO_NOTIFICATION_TRACKING=true\n", $contents, $file);
            $this->assertStringStartsWith('APP_NAME=', $contents, $file);
        }
    }

    public function test_running_it_again_changes_nothing(): void
    {
        file_put_contents($this->root.'/.env', "EXPO_ACCESS_TOKEN=secret\n");
        $this->artisan('expo-notification:install')->expectsConfirmation('Run the migrations now?', 'no');
        $config = file_get_contents($this->root.'/config/expo-notification.php').'// edited';
        file_put_contents($this->root.'/config/expo-notification.php', $config);
        $env = file_get_contents($this->root.'/.env');

        $this->artisan('expo-notification:install')
            ->expectsOutputToContain('The migrations are already published.')
            ->expectsConfirmation('Run the migrations now?', 'no')
            ->assertSuccessful();

        $this->assertCount(5, $this->migrations());
        $this->assertSame($config, file_get_contents($this->root.'/config/expo-notification.php'));
        $this->assertSame($env, file_get_contents($this->root.'/.env'));
        $this->assertStringContainsString('EXPO_ACCESS_TOKEN=secret', $env);
        $this->assertSame(1, substr_count($env, 'EXPO_ACCESS_TOKEN='));
    }

    public function test_an_upgrade_publishes_only_the_new_migrations(): void
    {
        $this->artisan('expo-notification:install', ['--without-env' => true])->expectsConfirmation('Run the migrations now?', 'no');
        $locale = collect(glob($this->root.'/database/migrations/*_add_locale_to_push_notifications.php'))->sole();
        unlink($locale);
        $before = $this->migrations();

        $this->artisan('expo-notification:install', ['--without-env' => true])
            ->expectsOutputToContain('Published 1 migration.')
            ->expectsConfirmation('Run the migrations now?', 'no')
            ->assertSuccessful();

        $this->assertCount(5, $this->migrations());
        $this->assertSame([], array_diff($before, $this->migrations()));
    }

    public function test_migrations_are_published_in_the_package_order(): void
    {
        $this->artisan('expo-notification:install', ['--without-env' => true])->expectsConfirmation('Run the migrations now?', 'no');

        $this->assertSame(
            ['create_push_tokens_table.php', 'create_push_notifications_table.php', 'create_push_notification_deliveries_table.php',
                'create_push_notification_events_table.php', 'add_locale_to_push_notifications.php'],
            array_map(fn (string $file) => substr($file, 18), $this->migrations()),
        );
    }

    public function test_without_env_leaves_the_env_files_alone(): void
    {
        file_put_contents($this->root.'/.env', "APP_NAME=Demo\n");

        $this->artisan('expo-notification:install', ['--without-env' => true])
            ->expectsConfirmation('Run the migrations now?', 'no')
            ->assertSuccessful();

        $this->assertSame("APP_NAME=Demo\n", file_get_contents($this->root.'/.env'));
        $this->assertFileDoesNotExist($this->root.'/.env.example');
    }

    public function test_it_can_run_the_migrations(): void
    {
        $this->artisan('expo-notification:install', ['--without-env' => true])
            ->expectsConfirmation('Run the migrations now?', 'yes')
            ->assertSuccessful();

        $this->assertTrue(Schema::hasTable('push_tokens'));
        $this->assertTrue(Schema::hasTable('push_notification_events'));
        $this->assertTrue(Schema::hasColumn('push_notification_deliveries', 'locale'));
    }
}
