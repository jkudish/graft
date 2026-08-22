<?php

declare(strict_types=1);

use Graft\Ai\AllowedRepository;

it('allows every repository when the allowlist is empty', function () {
    config(['graft.ai.allowed_repositories' => []]);

    expect(AllowedRepository::isAllowed('anyone/repo'))->toBeTrue();
    AllowedRepository::assertAllowed('anyone/repo');
});

it('matches owner/repo values case-insensitively', function () {
    config(['graft.ai.allowed_repositories' => ['Acme/App', 'jkudish/graft']]);

    expect(AllowedRepository::isAllowed('acme/app'))->toBeTrue()
        ->and(AllowedRepository::isAllowed('JKUDISH/GRAFT'))->toBeTrue()
        ->and(AllowedRepository::isAllowed('other/repo'))->toBeFalse();
});

it('throws when a repository is not allowlisted', function () {
    config(['graft.ai.allowed_repositories' => ['acme/app']]);

    expect(fn () => AllowedRepository::assertAllowed('other/repo'))
        ->toThrow(InvalidArgumentException::class, 'not allowlisted');
});
