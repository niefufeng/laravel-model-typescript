<?php

namespace NieFufeng\LaravelModelTypescript\Generators;

use NieFufeng\LaravelModelTypescript\Transit;

interface GeneratorContract
{
    public function generate(Transit $transit, \Closure $next);
}
