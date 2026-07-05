<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/config',
    ])
    // Optionnel : Charge les règles basées sur vos versions installées (Symfony 7.3)
    ->withComposerBased(symfony: true) 
    // OU utilisez des sets prédéfinis si vous voulez forcer un niveau de qualité
    // ->withPreparedSets(codeQuality: true, naming: true)
    ;