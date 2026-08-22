<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

/*
 * Shared vendor installs can classmap Graft to another worktree. Load this
 * checkout first so the suite always exercises the package under test.
 */
spl_autoload_register(static function (string $class): void {
    $map = [
        'Graft\\Tests\\' => __DIR__.DIRECTORY_SEPARATOR,
        'Graft\\' => dirname(__DIR__).DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR,
    ];

    foreach ($map as $prefix => $base) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
        $file = $base.$relative.'.php';

        if (is_file($file)) {
            require $file;

            return;
        }
    }
}, true, true);

require_once __DIR__.'/Helpers.php';
