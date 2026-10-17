<?php

namespace App\Jobs;

use App\Support\ProductImages;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateImageVariants implements ShouldQueue
{
    use Queueable;

    /** @param  array<int, string>  $paths */
    public function __construct(public array $paths) {}

    public function handle(): void
    {
        foreach ($this->paths as $path) {
            ProductImages::generate($path);
        }
    }
}
