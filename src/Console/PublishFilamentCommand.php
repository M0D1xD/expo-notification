<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Console;

use Filament\Panel;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * Copies the Filament section into the app (app/Filament/ExpoNotification) so it can be changed freely. The copy no
 * longer receives package updates; register its own ExpoNotificationPlugin instead of the package's.
 */
final class PublishFilamentCommand extends Command
{
    private const string NAMESPACE = 'M0d1xd\\ExpoNotification\\Filament';

    protected $signature = 'expo-notification:filament-publish
        {--force : Overwrite files that were already published}';

    protected $description = 'Copy the expo-notification Filament resources, widgets and actions into app/Filament';

    public function handle(Filesystem $files): int
    {
        if (! class_exists(Panel::class)) {
            $this->components->error('Filament is not installed: composer require filament/filament:"^5.0"');

            return self::FAILURE;
        }

        $source = dirname(__DIR__).'/Filament';
        $target = app_path('Filament/ExpoNotification');
        $namespace = $this->appNamespace().'Filament\\ExpoNotification';
        $written = $skipped = 0;

        foreach ($files->allFiles($source) as $file) {
            $path = $target.'/'.$file->getRelativePathname();

            if ($files->exists($path) && ! $this->option('force')) {
                $skipped++;

                continue;
            }

            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, str_replace(self::NAMESPACE, $namespace, $file->getContents()));
            $written++;
        }

        $this->components->info("Published {$written} files to app/Filament/ExpoNotification".($skipped ? ", skipped {$skipped} that already exist (--force to overwrite)." : '.'));
        $this->components->bulletList([
            'In your panel provider, replace <comment>'.self::NAMESPACE.'\\ExpoNotificationPlugin</comment> with <comment>'.$namespace.'\\ExpoNotificationPlugin</comment>.',
            'The copied classes do not receive package updates.',
        ]);

        return self::SUCCESS;
    }

    /** "App\\" unless composer.json maps app/ to another namespace. */
    private function appNamespace(): string
    {
        try {
            return $this->laravel->getNamespace();
        } catch (RuntimeException) {
            return 'App\\';
        }
    }
}
