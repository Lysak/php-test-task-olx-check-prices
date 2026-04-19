<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;

return RectorConfig::configure()
    ->withParallel()
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/app',
        __DIR__ . '/tests',
    ])
    // uncomment to reach your current PHP version
    // ->withPhpSets()
    ->withImportNames(removeUnusedImports: true) // auto-use statements
    ->withRules([
        ReadOnlyPropertyRector::class,
        ReadOnlyClassRector::class,
        NewMethodCallWithoutParenthesesRector::class,
    ])
    ->withTypeCoverageLevel(3)
    ->withDeadCodeLevel(3)
    ->withCodeQualityLevel(3);
