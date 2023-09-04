<?php

namespace NieFufeng\LaravelModelTypescript\Commands;

use Illuminate\Console\Command;
use NieFufeng\LaravelModelTypescript\Manager;

class GenerateCommand extends Command
{
    protected $signature = 'model-typescript:generate {modelPaths?} {output?}';

    protected $description = 'Generate Models TypeScript definitions';

    public function handle()
    {
        $modelPaths = $this->argument('modelPaths') ?? config('model-typescript.paths', app_path('Models'));
        $outputPath = $this->argument('output') ?? config('model-typescript.output_path', resource_path('js/models.d.ts'));

        (new Manager(
            is_string($modelPaths) ? explode(',', $modelPaths) : $modelPaths,
            $outputPath
        ))->execute();

        $this->info($outputPath . ' generate successful.');
    }
}
