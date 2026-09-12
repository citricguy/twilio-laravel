<?php

// Dependency resolution only: the supplied application source is not available.
// Run with PHP 8.5 and the extensions required by that application's manifest.
$root = dirname(__DIR__);
$target = $root.'/.cache/consumer';
if (! is_dir($target)) {
    mkdir($target, 0777, true);
}
$manifest = json_decode(file_get_contents($root.'/tests/Fixtures/consumer/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$manifest['require']['citricguy/twilio-laravel'] = '^3.0';
$manifest['repositories'] = [['type' => 'path', 'url' => $root, 'options' => ['versions' => ['citricguy/twilio-laravel' => '3.0.0']]]];
file_put_contents($target.'/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
// The fixture intentionally retains the application's scripts and constraints.
// --no-scripts/--no-plugins prevent them from executing during this solver check.
$composer = trim((string) shell_exec('command -v composer'));
if ($composer === '' || ! is_file($composer)) {
    fwrite(STDERR, "Composer executable was not found on PATH.\n");
    exit(1);
}
$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($composer).' update --dry-run --no-scripts --no-plugins --no-interaction --no-progress --working-dir='.escapeshellarg($target);
passthru($command, $status);
exit($status);
