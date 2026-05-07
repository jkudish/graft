<?php

declare(strict_types=1);

namespace Graft\Concerns;

use Graft\Exceptions\TagException;
use Illuminate\Support\Collection;

trait ManagesTags
{
    /**
     * Get all tags in the repository.
     *
     * @return Collection<int, string>
     */
    public function tags(string $repoPath): Collection
    {
        $output = $this->runAndReturn($repoPath, ['tag', '--list']);

        if (empty($output)) {
            return collect();
        }

        /** @var Collection<int, string> */
        return collect(explode("\n", $output))
            ->filter()
            ->values();
    }

    /**
     * Create a new tag.
     *
     * @throws TagException
     */
    public function createTag(string $repoPath, string $name, ?string $message = null, ?string $ref = null): void
    {
        $args = ['tag'];

        if ($message !== null) {
            $args[] = '-a';
            $args[] = $name;
            $args[] = '-m';
            $args[] = $message;
        } else {
            $args[] = $name;
        }

        if ($ref !== null) {
            $args[] = $ref;
        }

        try {
            $this->run($repoPath, $args);
        } catch (\Exception $e) {
            throw new TagException("Failed to create tag '{$name}': {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Delete a tag.
     *
     * @throws TagException
     */
    public function deleteTag(string $repoPath, string $name): void
    {
        try {
            $this->run($repoPath, ['tag', '-d', $name]);
        } catch (\Exception $e) {
            throw new TagException("Failed to delete tag '{$name}': {$e->getMessage()}", previous: $e);
        }
    }
}
