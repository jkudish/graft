<?php

namespace Graft\Tests\Concerns;

use Symfony\Component\Process\Process;

trait CreatesTestRepositories
{
    /** @var list<string> */
    protected array $testRepoPaths = [];

    protected function createTestRepository(bool $bare = false): string
    {
        $path = sys_get_temp_dir().'/graft-test-'.uniqid();
        mkdir($path, 0777, true);

        if ($bare) {
            $this->runGit($path, ['init', '--bare']);
        } else {
            $this->runGit($path, ['init']);
            $this->runGit($path, ['config', 'user.email', 'test@graft.dev']);
            $this->runGit($path, ['config', 'user.name', 'Graft Test']);
        }

        $this->testRepoPaths[] = $path;

        return $path;
    }

    protected function createTestRepositoryWithCommit(): string
    {
        $path = $this->createTestRepository();

        file_put_contents($path.'/README.md', '# Test Repository');
        $this->runGit($path, ['add', '.']);
        $this->runGit($path, ['commit', '-m', 'Initial commit']);

        return $path;
    }

    protected function createFileInRepo(string $path, string $filename, string $content = ''): void
    {
        $dir = dirname($path.'/'.$filename);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path.'/'.$filename, $content ?: "Content of {$filename}");
    }

    /**
     * @param  list<string>  $args
     */
    protected function runGit(string $path, array $args): string
    {
        $process = new Process(
            ['git', ...$args],
            $path,
        );
        $process->run();

        return trim($process->getOutput());
    }

    /**
     * @after
     */
    protected function cleanupTestRepositories(): void
    {
        foreach ($this->testRepoPaths as $path) {
            if (is_dir($path)) {
                $this->removeDirectory($path);
            }
        }

        $this->testRepoPaths = [];
    }

    protected function removeDirectory(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($path);
    }

    /**
     * Helper to get the main branch name (main or master).
     */
    protected function getMainBranch(string $repo): string
    {
        $output = $this->runGit($repo, ['branch']);
        $branches = explode("\n", $output);
        $currentBranch = '';

        foreach ($branches as $branch) {
            if (str_starts_with($branch, '*')) {
                $currentBranch = trim(substr($branch, 1));
                break;
            }
        }

        return $currentBranch ?: 'main';
    }
}
