<?php

namespace Ciplnew\BugTracking;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Throwable;

class BugTrackServiceProvider extends ServiceProvider
{
    public const VERSION = '1.0.0';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bugtracking.php', 'bugtracking');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/bugtracking.php' => config_path('bugtracking.php'),
            ], 'bugtracking-config');
        }

        $this->app->afterResolving(ExceptionHandler::class, function (ExceptionHandler $handler) {
            if (! method_exists($handler, 'reportable')) {
                return;
            }

            $handler->reportable(function (Throwable $e) {
                BugTrackReporter::report($e);
            });
        });
    }
}
