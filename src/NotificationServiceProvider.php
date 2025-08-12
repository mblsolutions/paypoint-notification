<?php

namespace MBLSolutions\Notification;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use MBLSolutions\Notification\Console\CreateNotificationLogsTableCommand;
use MBLSolutions\Notification\Mailer\PayPointNotificationTransport;
use MBLSolutions\Notification\Services\PayPointNotificationService;

class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Mail::extend('paypoint', function (array $config = []) {

            $payPointNotificationService = new PayPointNotificationService($config['endpoint'],$config['subscription_key']);

            return new PayPointNotificationTransport($payPointNotificationService);
        });

        // Publish the package config
        $this->publishes([
            __DIR__ . '/../config/notification.php' => config_path('notification.php'),
        ], 'notification-config');

        // Register the command if we are using the application via the CLI
        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateNotificationLogsTableCommand::class,
            ]);
        }
    }
}