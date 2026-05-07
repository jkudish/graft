<?php

declare(strict_types=1);

use Graft\Contracts\GitManager;
use Graft\Contracts\PlatformProvider;
use Graft\Data\Git\Remote;
use Graft\GraftManager;

test('git returns the git manager', function () {
    $git = Mockery::mock(GitManager::class);
    $manager = new GraftManager($git, config('graft'));

    expect($manager->git())->toBe($git);
});

test('platform returns default provider', function () {
    $git = Mockery::mock(GitManager::class);
    $config = config('graft');
    $config['platform']['providers']['github']['token'] = 'test-token';
    $manager = new GraftManager($git, $config);

    $provider = $manager->platform();

    expect($provider)->toBeInstanceOf(PlatformProvider::class);
});

test('platform caches provider instances', function () {
    $git = Mockery::mock(GitManager::class);
    $config = config('graft');
    $config['platform']['providers']['github']['token'] = 'test-token';
    $manager = new GraftManager($git, $config);

    $first = $manager->platform();
    $second = $manager->platform();

    expect($first)->toBe($second);
});

test('platform throws for unconfigured provider', function () {
    $git = Mockery::mock(GitManager::class);
    $manager = new GraftManager($git, config('graft'));

    $manager->platform('gitlab');
})->throws(RuntimeException::class, 'not configured');

test('platformFor resolves github from remote URL', function () {
    $git = Mockery::mock(GitManager::class);
    $git->shouldReceive('remotes')
        ->with('/tmp/repo')
        ->andReturn(collect([
            new Remote('origin', 'https://github.com/jkudish/ops.git'),
        ]));

    $config = config('graft');
    $config['platform']['providers']['github']['token'] = 'test-token';
    $manager = new GraftManager($git, $config);

    $provider = $manager->platformFor('/tmp/repo');

    expect($provider)->toBeInstanceOf(PlatformProvider::class);
});

test('platformFor falls back to default when no origin', function () {
    $git = Mockery::mock(GitManager::class);
    $git->shouldReceive('remotes')
        ->with('/tmp/repo')
        ->andReturn(collect());

    $config = config('graft');
    $config['platform']['providers']['github']['token'] = 'test-token';
    $manager = new GraftManager($git, $config);

    $provider = $manager->platformFor('/tmp/repo');

    expect($provider)->toBeInstanceOf(PlatformProvider::class);
});
