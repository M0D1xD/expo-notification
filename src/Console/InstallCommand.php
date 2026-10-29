<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * One-step setup: publishes the config and migrations, adds the EXPO_* keys to .env and .env.example, and offers to
 * migrate. Safe to run again: nothing that already exists is overwritten or published twice.
 */
final class InstallCommand extends Command
{
    /** The environment keys the package reads, with the default written for each and a comment. */
    private const array ENV = [
        'EXPO_ACCESS_TOKEN' => ['', 'Only if "Enhanced Security for Push Notifications" is on for the project on expo.dev'],
        'EXPO_NOTIFICATION_TRACKING' => ['true', 'false for apps that do not use expo-notifications-client (Android pushes are data-only otherwise)'],
    ];

    protected $signature = 'expo-notification:install
        {--force : Overwrite the published config}
        {--without-env : Do not touch .env or .env.example}';

    protected $description = 'Publish the expo-notification config and migrations and add its environment variables';

    public function handle(Filesystem $files): int
    {
        $this->call('vendor:publish', ['--tag' => 'expo-notification-config', '--force' => (bool) $this->option('force')]);
        $this->publishMigrations($files);

        if (! $this->option('without-env')) {
            $this->addEnvironmentVariables($files);
        }

        if ($this->confirm('Run the migrations now?', true)) {
            $this->call('migrate');
        }

        $this->components->info('expo-notification is installed.');
        $this->components->bulletList([
            'Add <comment>HasPushTokens</comment> to the model that owns devices (e.g. App\\Models\\User).',
            'Run the scheduler so receipts are checked: <comment>php artisan schedule:work</comment> or a cron entry.',
            'Set up the app with <comment>expo-notifications-client</comment>.',
        ]);

        return self::SUCCESS;
    }

    /** Publishing again would copy the migrations under new dates, so they are published only once. */
    private function publishMigrations(Filesystem $files): void
    {
        $published = collect($files->glob(database_path('migrations/*.php')))
            ->contains(fn (string $path) => str_ends_with($path, '_create_push_tokens_table.php'));

        if ($published) {
            $this->components->info('The migrations are already published.');

            return;
        }

        $this->call('vendor:publish', ['--tag' => 'expo-notification-migrations']);
    }

    /** Appends the keys a file does not have yet; existing values are never changed. */
    private function addEnvironmentVariables(Filesystem $files): void
    {
        $env = $this->laravel->environmentFilePath();

        foreach ([$env.'.example', $env] as $path) {
            if (! $files->exists($path)) {
                continue;
            }

            $contents = $files->get($path);
            $missing = array_filter(
                self::ENV,
                fn (string $key) => preg_match('/^\s*'.preg_quote($key, '/').'\s*=/m', $contents) !== 1,
                ARRAY_FILTER_USE_KEY,
            );

            if ($missing === []) {
                continue;
            }

            $block = collect($missing)
                ->map(fn (array $entry, string $key) => "# {$entry[1]}\n{$key}={$entry[0]}")
                ->implode("\n");
            $files->append($path, (str_ends_with($contents, "\n") || $contents === '' ? '' : "\n")."\n{$block}\n");

            $this->components->info('Added '.implode(', ', array_keys($missing)).' to '.basename($path).'.');
        }
    }
}
