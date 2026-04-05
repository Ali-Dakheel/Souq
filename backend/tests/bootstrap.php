<?php

// The vendor directory is a junction to the main backend vendor.
// Application::inferBasePath() uses ClassLoader::getRegisteredLoaders() which
// resolves the junction to its real target (main backend), causing Laravel to
// boot from the main backend's bootstrap/app.php and use its database path.
// Setting APP_BASE_PATH forces inferBasePath() to use the worktree path instead.
$_ENV['APP_BASE_PATH'] = dirname(__DIR__);
$_SERVER['APP_BASE_PATH'] = dirname(__DIR__);

// Load the vendor autoloader (junction → main backend vendor)
$loader = require __DIR__.'/../vendor/autoload.php';

// The junction makes the autoloader resolve App\ to the main backend's app/.
// In the worktree, new module files live here — add the worktree's app/ so
// new classes are found without touching the shared vendor directory.
$worktreeApp = dirname(__DIR__).'/app';
$loader->addPsr4('App\\', $worktreeApp, true); // prepend = true (takes priority)

// Composer's classmap takes priority over PSR-4, so we must also redirect
// classmap entries for App\ classes to the worktree's app/.
// We derive the worktree file path from the class name directly
// to avoid dealing with the unresolved ../.. paths in the classmap.
$overrides = [];
foreach (array_keys($loader->getClassMap()) as $class) {
    if (! str_starts_with($class, 'App\\')) {
        continue;
    }
    // App\Modules\Foo\Bar => {worktreeApp}/Modules/Foo/Bar.php
    $relative = DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 4)).'.php';
    $newPath = $worktreeApp.$relative;
    if (file_exists($newPath)) {
        $overrides[$class] = $newPath;
    }
}
if ($overrides) {
    $loader->addClassMap($overrides);
}
