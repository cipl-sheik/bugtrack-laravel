<?php

namespace Ciplnew\BugTracking;

use Illuminate\Support\ServiceProvider;

class BugTrackServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //register handler
        $this->app->make('Ciplnew\BugTracking\BugTrackController');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        include __DIR__.'/routes/web.php';
    }
}
