<?php

namespace NieFufeng\LaravelModelTypescript\Commands;

use Illuminate\Console\Command;
use NieFufeng\LaravelModelTypescript\Manager;

class GenerateCommand extends Command
{
    protected $signature = 'typescript:generate';

    protected $description = 'Generate Models TypeScript definitions';

    public function handle()
    {
        (new Manager(
            config('typescript.paths', [app_path('Models')]),
            config('typescript.output_path', resource_path('js/models.d.ts')))
        )->execute();

        $this->info(config('typescript.output_path', resource_path('js/models.d.ts')) . ' generate successful.');
    }
}
