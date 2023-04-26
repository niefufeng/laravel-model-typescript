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
            __DIR__ . '/../configs/typescript.php', 'typescript'
        );

        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../configs/typescript.php' => config_path('typescript.php'),
        ], 'model-typescript-config');

        $this->commands([
            GenerateCommand::class,
        ]);
    }
}
