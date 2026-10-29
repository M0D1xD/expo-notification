<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use M0d1xd\ExpoNotification\Console\CheckReceiptsCommand;
use M0d1xd\ExpoNotification\Console\SendTestNotificationCommand;

final class ExpoNotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/expo-notification.php', 'expo-notification');

        $this->app->singleton(ExpoClient::class, fn ($app) => new ExpoClient(
            $app->make(Factory::class),
            $app->make(Dispatcher::class),
            $app['config']->get('expo-notification'),
        ));

        $this->app->singleton(PushTokenRegistry::class);
        $this->app->singleton(PushNotifier::class);
        $this->app->singleton(ReceiptChecker::class);
        $this->app->singleton(EventRecorder::class);
    }

    public function boot(): void
    {
        if ($this->app['config']->get('expo-notification.routes.enabled')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            if ($cron = $this->app['config']->get('expo-notification.receipts.schedule')) {
                $schedule->command(CheckReceiptsCommand::class)->cron($cron)->withoutOverlapping();
            }
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/expo-notification.php' => config_path('expo-notification.php'),
        ], 'expo-notification-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'expo-notification-migrations');

        $this->commands([SendTestNotificationCommand::class, CheckReceiptsCommand::class]);
    }
}
