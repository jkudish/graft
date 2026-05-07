# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
