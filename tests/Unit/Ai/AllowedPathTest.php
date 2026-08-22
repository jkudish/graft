<?php

declare(strict_types=1);

use Graft\Ai\AllowedPath;

it('resolves an allowlisted root and its subdirectory', function () {
    $root = sys_get_temp_dir().'/graft-allowed-'.uniqid();
    $child = $root.'/child';
    mkdir($child, 0777, true);

    config([
        'graft.ai.allowed_repos' => [$root],
        'graft.ai.allow_base_path' => false,
    ]);

    expect(AllowedPath::resolve($root))->toBe(realpath($root))
        ->and(AllowedPath::resolve($child))->toBe(realpath($child));

    @rmdir($child);
    @rmdir($root);
});

it('rejects paths outside the allowlist', function () {
    $root = sys_get_temp_dir().'/graft-allowed-'.uniqid();
    mkdir($root, 0777, true);

    config([
        'graft.ai.allowed_repos' => [$root],
        'graft.ai.allow_base_path' => false,
    ]);

    expect(fn () => AllowedPath::resolve(sys_get_temp_dir()))
        ->toThrow(InvalidArgumentException::class, 'not allowlisted');

    @rmdir($root);
});

it('defaults an empty path to base_path when allow_base_path is true', function () {
    config([
        'graft.ai.allowed_repos' => [],
        'graft.ai.allow_base_path' => true,
    ]);

    expect(AllowedPath::resolve(''))->toBe(realpath(base_path()))
        ->and(AllowedPath::resolve(base_path()))->toBe(realpath(base_path()));
});

it('denies every path when the allowlist is empty and allow_base_path is false', function () {
    config([
        'graft.ai.allowed_repos' => [],
        'graft.ai.allow_base_path' => false,
    ]);

    expect(fn () => AllowedPath::resolve(''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => AllowedPath::resolve(base_path()))->toThrow(InvalidArgumentException::class);
});

it('still allows base_path when a non-empty allowlist also enables allow_base_path', function () {
    $root = sys_get_temp_dir().'/graft-allowed-'.uniqid();
    mkdir($root, 0777, true);

    config([
        'graft.ai.allowed_repos' => [$root],
        'graft.ai.allow_base_path' => true,
    ]);

    expect(AllowedPath::resolve(base_path()))->toBe(realpath(base_path()));

    @rmdir($root);
});

it('canonicalizes traversal so a path cannot escape an allowlisted root', function () {
    $root = sys_get_temp_dir().'/graft-allowed-'.uniqid();
    mkdir($root, 0777, true);

    config([
        'graft.ai.allowed_repos' => [$root],
        'graft.ai.allow_base_path' => false,
    ]);

    expect(fn () => AllowedPath::resolve($root.'/../'.basename(sys_get_temp_dir())))
        ->toThrow(InvalidArgumentException::class);

    @rmdir($root);
});
