<?php

namespace NieFufeng\LaravelModelTypescript;

use NieFufeng\LaravelModelTypescript\Commands\GenerateCommand;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
    }

    public function boot()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../configs/typescript.php', 'model-typescript'
        );

        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../configs/typescript.php' => config_path('model-typescript.php'),
        ], 'model-typescript');

        $this->commands([
            GenerateCommand::class,
        ]);
    }
}
