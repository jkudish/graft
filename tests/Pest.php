<?php

// Laravel TestCase is applied per file via uses(TestCase::class). A global
// uses()->in() here would miss worktree tests when Pest resolves rootPath
// through the vendor symlink, and would double-apply (TestCaseAlreadyInUse)
// when rootPath is this checkout.

pest()->tia()->always()->locally();
