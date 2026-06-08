<?php

declare(strict_types=1);

$targets = [
    __DIR__.'/../vendor/laravel/framework/src/Illuminate',
    __DIR__.'/../vendor/opis/closure',
    __DIR__.'/../vendor/psy/psysh/src',
    __DIR__.'/../vendor/vlucas/phpdotenv/src',
];

$signaturePattern = '/function\s*(?:&\s*)?(?:[A-Za-z_][A-Za-z0-9_]*)?\s*\((.*?)\)(?=\s*(?::|use\s*\(|\{|;))/s';
$nullableTypedParameterPattern = '/(?<![\?\w])((?:\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*|array|callable|iterable|string|int|float|bool|self|parent))(\s*&?\s*\$[A-Za-z_][A-Za-z0-9_]*\s*=\s*null)\b/i';

$scanned = 0;
$patched = 0;

foreach ($targets as $target) {
    if (! is_dir($target)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $scanned++;

        $path = $file->getPathname();
        $original = file_get_contents($path);

        if ($original === false || str_contains($original, '= null') === false) {
            continue;
        }

        $updated = preg_replace_callback(
            $signaturePattern,
            static function (array $matches) use ($nullableTypedParameterPattern): string {
                $parameters = $matches[1];
                $rewritten = preg_replace($nullableTypedParameterPattern, '?$1$2', $parameters);

                if ($rewritten === null || $rewritten === $parameters) {
                    return $matches[0];
                }

                return str_replace($parameters, $rewritten, $matches[0]);
            },
            $original
        );

        if ($updated === null || $updated === $original) {
            continue;
        }

        file_put_contents($path, $updated);
        $patched++;
    }
}

$handleExceptionsPath = __DIR__.'/../vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php';
$composerScriptsPath = __DIR__.'/../vendor/laravel/framework/src/Illuminate/Foundation/ComposerScripts.php';

if (is_file($handleExceptionsPath)) {
    $original = file_get_contents($handleExceptionsPath);
    $replacement = <<<'PHP'
error_reporting(PHP_VERSION_ID >= 80400
            ? E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED
            : -1);
PHP;

    $updated = $original === false ? false : str_replace('error_reporting(-1);', $replacement, $original);

    if (is_string($updated) && $updated !== $original) {
        file_put_contents($handleExceptionsPath, $updated);
    }
}

if (is_file($composerScriptsPath)) {
    $original = file_get_contents($composerScriptsPath);
    $search = "    {\n        require_once \$event->getComposer()->getConfig()->get('vendor-dir').'/autoload.php';";
    $replace = <<<'PHP'
    {
        error_reporting(PHP_VERSION_ID >= 80400
            ? E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED
            : -1);

        require_once $event->getComposer()->getConfig()->get('vendor-dir').'/autoload.php';
PHP;

    $updated = $original === false ? false : str_replace($search, $replace, $original);

    if (is_string($updated) && $updated !== $original) {
        file_put_contents($composerScriptsPath, $updated);
    }
}

fwrite(STDOUT, sprintf(
    "Laravel PHP 8.4 compatibility patch complete: %d file(s) patched across %d scanned file(s).\n",
    $patched,
    $scanned
));
