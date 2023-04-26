<?php

namespace NieFufeng\LaravelModelTypescript;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Pipeline;
use Illuminate\Support\Str;
use NieFufeng\LaravelModelTypescript\Exceptions\GenerateException;
use NieFufeng\LaravelModelTypescript\Generators\AccessorsGenerator;
use NieFufeng\LaravelModelTypescript\Generators\DatabaseColumnsGenerator;
use NieFufeng\LaravelModelTypescript\Generators\RelationsGenerator;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Manager
{
    /**
     * @var Collection<class-string, TypeScriptDefinition>
     */
    protected Collection $generatedModels;

    public function __construct(
        protected readonly array  $paths,
        protected readonly string $outputPath
    )
    {
        $this->generatedModels = collect();
    }

    public function execute(): void
    {
        $queue = new Queue();

        $queue->push(...$this->getModels()->values());

        while ($current = $queue->pop()) {
            /** @var ReflectionClass|null $current */
            if ($this->modelIsGenerated($current)) {
                continue;
            }

            $this->generatedModels[$current->name] = Pipeline::send(new Transit($current, $queue))
                ->via('generate')
                ->through([
                    DatabaseColumnsGenerator::class,
                    AccessorsGenerator::class,
                    RelationsGenerator::class
                ])
                ->thenReturn();
        }

        $output = $this->generatedModels
            ->groupBy(fn(Transit $transit) => $transit->reflection->getNamespaceName())
            ->map(function (Collection $transits, string $namespace) {
                return collect([
                    'declare namespace ' . str_replace('\\', '.', $namespace) . ' {',
                    $transits->map(fn(Transit $transit) => (string)$transit)->join(PHP_EOL),
                    '}'
                ])->join(PHP_EOL);
            })
            ->values()
            ->join(PHP_EOL);

        file_put_contents($this->outputPath, $output);
    }

    protected function modelIsGenerated(ReflectionClass $model): bool
    {
        return $this->generatedModels->has($model->name);
    }

    /**
     * @return Collection<int, ReflectionClass>
     */
    protected function getModels(): Collection
    {
        return collect($this->paths)
            ->flatMap(fn(string $path) => collect((new Finder)->in($path)->name('*.php')->files())
                ->map(fn(SplFileInfo $file) => $this->getNamespaceFromFile($file) . '\\' . str_replace(
                        ['/', '.php'],
                        ['\\', ''],
                        Str::after($file->getRealPath(), realpath($path) . DIRECTORY_SEPARATOR)
                    ))
                ->filter(function (string $model) {
                    try {
                        return new $model() instanceof Model;
                    } catch (\Throwable) {
                        return false;
                    }
                })
                ->values()
            );
    }

    /**
     * @throws GenerateException
     */
    protected function getNamespaceFromFile(SplFileInfo $file): string
    {
        $content = $file->getContents();

        $pattern = '/namespace\s+(.*?);/';

        if (preg_match($pattern, $content, $matched) !== 1) {
            throw new GenerateException('file [' . $file->getRealPath() . '] no namespace.');
        }

        return trim($matched[1]);
    }
}
