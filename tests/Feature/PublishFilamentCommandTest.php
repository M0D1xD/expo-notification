<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use M0d1xd\ExpoNotification\Tests\TestCase;

class PublishFilamentCommandTest extends TestCase
{
    private string $app_path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app_path = sys_get_temp_dir().'/expo-notification-filament-'.uniqid();
        $this->app->useAppPath($this->app_path);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->app_path);

        parent::tearDown();
    }

    public function test_it_copies_the_section_under_the_app_namespace(): void
    {
        $this->artisan('expo-notification:filament-publish')->assertSuccessful();

        $target = $this->app_path.'/Filament/ExpoNotification';
        $files = (new Filesystem)->allFiles($target);
        $this->assertCount(count((new Filesystem)->allFiles(__DIR__.'/../../src/Filament')), $files);

        foreach ($files as $file) {
            $this->assertStringNotContainsString('M0d1xd\\ExpoNotification\\Filament', $file->getContents(), $file->getRelativePathname());
            exec('php -l '.escapeshellarg($file->getPathname()), $output, $status);
            $this->assertSame(0, $status, $file->getRelativePathname());
        }

        $plugin = file_get_contents($target.'/ExpoNotificationPlugin.php');
        $this->assertStringContainsString('namespace App\\Filament\\ExpoNotification;', $plugin);
        $this->assertStringContainsString('use App\\Filament\\ExpoNotification\\Resources\\PushTokens\\PushTokenResource;', $plugin);
        $this->assertStringContainsString('use M0d1xd\\ExpoNotification\\Models\\PushToken;', file_get_contents($target.'/Resources/PushTokens/PushTokenResource.php'));
    }

    public function test_it_keeps_changed_files_unless_forced(): void
    {
        $this->artisan('expo-notification:filament-publish')->assertSuccessful();
        $display = $this->app_path.'/Filament/ExpoNotification/Support/Display.php';
        file_put_contents($display, '<?php // changed');

        $this->artisan('expo-notification:filament-publish')
            ->expectsOutputToContain('skipped')
            ->assertSuccessful();
        $this->assertSame('<?php // changed', file_get_contents($display));

        $this->artisan('expo-notification:filament-publish', ['--force' => true])->assertSuccessful();
        $this->assertStringContainsString('class Display', file_get_contents($display));
    }
}
