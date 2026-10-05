<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('tem um componente Vue para cada Inertia::render do backend', function (): void {
    $root = dirname(__DIR__, 2);
    $missing = [];

    foreach ((new Finder)->files()->in([$root.'/app', $root.'/routes'])->name('*.php') as $file) {
        preg_match_all("/Inertia::render\\(\\s*'([^']+)'/", $file->getContents(), $matches);

        foreach ($matches[1] as $component) {
            if (! file_exists($root.'/resources/js/Pages/'.$component.'.vue')) {
                $missing[] = $component.' ('.$file->getRelativePathname().')';
            }
        }
    }

    expect($missing)->toBe([]);
});
