<?php

namespace Iafilin\EloquentHttpAdapter;

use Illuminate\Support\ServiceProvider;

class EloquentHttpAdapterServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'iafilin');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'iafilin');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/eloquent-http-adapter.php', 'eloquent-http-adapter');

        $this->app->singleton('http-model', function ($app) {
            return new Support\HttpModelManager();
        });

        // Server-side helpers
        $this->app->singleton(\Iafilin\EloquentHttpAdapter\Server\QueryApplier::class, function ($app) {
            return new \Iafilin\EloquentHttpAdapter\Server\QueryApplier();
        });
        $this->app->singleton(\Iafilin\EloquentHttpAdapter\Server\HttpResponder::class, function ($app) {
            return new \Iafilin\EloquentHttpAdapter\Server\HttpResponder();
        });
    }

    /**
     * Console-specific booting.
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/eloquent-http-adapter.php' => config_path('eloquent-http-adapter.php'),
        ], 'eloquent-http-adapter.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/iafilin'),
        ], 'eloquent-http-adapter.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/iafilin'),
        ], 'eloquent-http-adapter.assets');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/iafilin'),
        ], 'eloquent-http-adapter.lang');*/

        // Registering package commands.
        // $this->commands([]);
    }
}
