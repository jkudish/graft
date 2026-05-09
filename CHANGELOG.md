# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-05-08

### Added

- `Graft\Ai\Contracts\IdentifiableTool` interface — gives tools a stable colon-notation identifier (e.g. `graft:git:log`) for discovery, MCP routing, and container tagging
- AI tools are now publicly usable: `GitLogTool`, `GitStatusTool`, `GitDiffTool`, `GitBranchesTool`, `GitHubListPrsTool`, `GitHubGetIssueTool`, `GitHubCreateIssueTool`, `GitHubListIssuesTool`, `GitHubPrReviewTool`. Each implements both `Laravel\Ai\Contracts\Tool` and `Graft\Ai\Contracts\IdentifiableTool`
- README section documenting the AI tools, their `toolId()` strings, and registration with `Laravel\Ai\Agent`
- Test coverage for all 9 AI tools using `Git::fake()` / `GitHub::fake()` (52 new tests)

### Changed

- AI tools no longer reference the host application's `App\Ai\Contracts\IdentifiableTool` — the contract now lives entirely inside the package and works for any Packagist consumer
- Removed the temporary PHPStan exclusion of `src/Ai/Tools/` (no longer needed)

## [0.1.2] - 2026-05-07

### Added

- GitHub Actions CI: tests workflow (PHP 8.2, 8.3, 8.4) and code-quality workflow (PHPStan + Pint check)
- README badges for build status, tests, and code quality

## [0.1.1] - 2026-05-07

### Added

- `symfony/process` constraint widened to allow `^7.0 || ^8.0`

## [0.1.0] - 2026-05-07

### Added

- `Git` facade for local git operations (branches, commits, index, remotes, merge, rebase, cherry-pick, tags, stash, worktrees, blame, clean)
- `GitHub` facade for GitHub API operations (pull requests, issues, reviews, comments, CI status, labels)
- `ScopedRepository` pattern via `Git::repo($path)` for binding git and platform operations to a single path
- Active objects on `PullRequest` and `Issue` DTOs with action methods
- `Git::fake()` and `GitHub::fake()` test fakes with semantic assertions
- Typed readonly DTOs for all git and platform data
- Exception hierarchy with contextual error data (`MergeConflictException`, `PlatformException`, etc.)
- Configuration for git binary path, process timeout, and platform provider settings
- Laravel 13 support (`illuminate/http` and `illuminate/support` now allow `^13.0`)
