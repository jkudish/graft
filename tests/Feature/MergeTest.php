<?php

declare(strict_types=1);

use Graft\Data\Git\MergeResult;
use Graft\Exceptions\ProcessException;
use Graft\ProcessGitManager;
use Graft\Tests\Concerns\CreatesTestRepositories;
use Symfony\Component\Process\Process;

uses(CreatesTestRepositories::class);

/**
 * Records buildProcess args and env so we can assert merge() no longer
 * constructs a raw Process outside ProcessGitManager.
 */
class RecordingProcessGitManager extends ProcessGitManager
{
    /** @var list<array{args: list<string>, env: array<string, string>}> */
    public array $invocations = [];

    protected function buildProcess(string $repoPath, array $args, ?int $timeout = null, array $extraEnv = []): Process
    {
        $process = parent::buildProcess($repoPath, $args, $timeout, $extraEnv);
        $this->invocations[] = [
            'args' => $args,
            'env' => $process->getEnv(),
        ];

        return $process;
    }
}

beforeEach(function () {
    $this->git = new ProcessGitManager;
});

test('successful fast-forward merge', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $mainBranch = $this->getMainBranch($repo);

    // Create feature branch
    $this->runGit($repo, ['checkout', '-b', 'feature']);
    $this->createFileInRepo($repo, 'feature.txt', 'feature content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Add feature']);

    // Switch back to main and merge
    $this->runGit($repo, ['checkout', $mainBranch]);

    $result = $this->git->merge($repo, 'feature');

    expect($result)->toBeInstanceOf(MergeResult::class)
        ->and($result->success)->toBeTrue()
        ->and($result->conflicts)->toBeEmpty();

    // Verify the feature file exists after merge
    expect(file_exists($repo.'/feature.txt'))->toBeTrue();
});

test('merge with --no-ff creates merge commit', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $mainBranch = $this->getMainBranch($repo);

    // Create feature branch and add commit
    $this->runGit($repo, ['checkout', '-b', 'feature']);
    $this->createFileInRepo($repo, 'feature.txt', 'feature content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Add feature']);

    // Switch back to main and add a commit there too (to prevent fast-forward)
    $this->runGit($repo, ['checkout', $mainBranch]);
    $this->createFileInRepo($repo, 'main.txt', 'main content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Add main file']);

    // Merge with --no-ff
    $result = $this->git->merge($repo, 'feature', noFf: true);

    expect($result)->toBeInstanceOf(MergeResult::class)
        ->and($result->success)->toBeTrue()
        ->and($result->conflicts)->toBeEmpty();

    // Verify merge commit was created (has 2 parents)
    $commit = $this->git->show($repo, 'HEAD');
    expect($commit->parents)->toHaveCount(2);
});

test('merge with custom message', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $mainBranch = $this->getMainBranch($repo);

    // Create feature branch and add commit
    $this->runGit($repo, ['checkout', '-b', 'feature']);
    $this->createFileInRepo($repo, 'feature.txt', 'feature content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Add feature']);

    // Switch back to main and add a commit there too (to prevent fast-forward)
    $this->runGit($repo, ['checkout', $mainBranch]);
    $this->createFileInRepo($repo, 'main.txt', 'main content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Add main file']);

    // Merge with custom message and --no-ff
    $result = $this->git->merge($repo, 'feature', 'Custom merge message', noFf: true);

    expect($result)->toBeInstanceOf(MergeResult::class)
        ->and($result->success)->toBeTrue();

    $commit = $this->git->show($repo, 'HEAD');
    expect($commit->message)->toBe('Custom merge message');
});

test('merge with conflict returns MergeResult with success=false and conflicts array', function () {
    $repo = $this->createTestRepositoryWithCommit();

    // Create conflicting changes
    $mainBranch = $this->getMainBranch($repo);

    // Modify README on main
    $this->createFileInRepo($repo, 'README.md', 'Main branch content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Update README on main']);

    // Create feature branch from before the main change
    $this->runGit($repo, ['checkout', 'HEAD~1']);
    $this->runGit($repo, ['checkout', '-b', 'feature']);
    $this->createFileInRepo($repo, 'README.md', 'Feature branch content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Update README on feature']);

    // Switch back to main and try to merge
    $this->runGit($repo, ['checkout', $mainBranch]);

    $result = $this->git->merge($repo, 'feature');

    expect($result)->toBeInstanceOf(MergeResult::class)
        ->and($result->success)->toBeFalse()
        ->and($result->conflicts)->toContain('README.md')
        ->and($result->message)->toContain('CONFLICT');
});

test('mergeAbort cancels an in-progress merge', function () {
    $repo = $this->createTestRepositoryWithCommit();

    // Create conflicting changes
    $mainBranch = $this->getMainBranch($repo);

    // Modify README on main
    $this->createFileInRepo($repo, 'README.md', 'Main branch content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Update README on main']);

    // Create feature branch from before the main change
    $this->runGit($repo, ['checkout', 'HEAD~1']);
    $this->runGit($repo, ['checkout', '-b', 'feature']);
    $this->createFileInRepo($repo, 'README.md', 'Feature branch content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Update README on feature']);

    // Switch back to main and create conflict
    $this->runGit($repo, ['checkout', $mainBranch]);
    $result = $this->git->merge($repo, 'feature');
    expect($result->success)->toBeFalse();

    // Abort the merge
    $this->git->mergeAbort($repo);

    // Verify no merge is in progress
    $status = $this->runGit($repo, ['status', '--porcelain']);
    expect($status)->toBe('');
});

test('merge of unknown branch throws ProcessException', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $this->git->merge($repo, 'does-not-exist');
})->throws(ProcessException::class);

test('merge runs through ProcessGitManager with prompt-disabled env', function () {
    $git = new RecordingProcessGitManager;
    $repo = $this->createTestRepositoryWithCommit();

    $mainBranch = $this->getMainBranch($repo);

    $this->runGit($repo, ['checkout', '-b', 'feature']);
    $this->createFileInRepo($repo, 'feature.txt', 'feature content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Add feature']);
    $this->runGit($repo, ['checkout', $mainBranch]);

    $result = $git->merge($repo, 'feature');

    expect($result->success)->toBeTrue();

    $mergeCall = collect($git->invocations)->first(
        fn (array $invocation): bool => ($invocation['args'][0] ?? null) === 'merge'
    );

    expect($mergeCall)->not->toBeNull()
        ->and($mergeCall['args'])->toBe(['merge', 'feature'])
        ->and($mergeCall['env'])->toHaveKey('GIT_TERMINAL_PROMPT', '0')
        ->and($mergeCall['env'])->toHaveKey('GCM_INTERACTIVE', 'never');
});
