<?php

/*
 * Checks that this PHP can run LeadEngine. With --fix (used by setup.bat on Windows),
 * it switches on missing extensions in php.ini, creating php.ini from
 * php.ini-development when PHP has none yet. A backup of php.ini is kept.
 *
 * Exit code 0 = ready, 1 = something is still missing.
 */

$required = ['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo_sqlite', 'sqlite3', 'tokenizer', 'xml', 'zip'];
$fix = in_array('--fix', $argv, true);
$ok = true;

if (PHP_VERSION_ID < 80300) {
    fwrite(STDOUT, 'PHP '.PHP_VERSION.' is too old. LeadEngine needs PHP 8.3 or newer.'.PHP_EOL);
    exit(1);
}

$missing = array_values(array_filter($required, fn ($ext) => ! extension_loaded($ext)));

if ($missing === []) {
    fwrite(STDOUT, 'PHP '.PHP_VERSION.' is ready.'.PHP_EOL);
    exit(0);
}

fwrite(STDOUT, 'PHP is missing these extensions: '.implode(' ', $missing).PHP_EOL);

if (! $fix) {
    fwrite(STDOUT, 'Enable them in your php.ini ('.(php_ini_loaded_file() ?: 'none loaded').'), then run this again.'.PHP_EOL);
    exit(1);
}

$phpDir = dirname(PHP_BINARY);
$ini = php_ini_loaded_file();

if (! $ini) {
    $ini = $phpDir.DIRECTORY_SEPARATOR.'php.ini';
    $template = $phpDir.DIRECTORY_SEPARATOR.'php.ini-development';

    if (! is_file($template)) {
        fwrite(STDOUT, "PHP has no php.ini and there is no php.ini-development in {$phpDir} to start from.".PHP_EOL);
        exit(1);
    }

    if (! @copy($template, $ini)) {
        fwrite(STDOUT, "Could not create {$ini}. Run setup.bat as administrator, or copy php.ini-development to php.ini yourself.".PHP_EOL);
        exit(1);
    }

    fwrite(STDOUT, "Created {$ini} from php.ini-development.".PHP_EOL);
} else {
    @copy($ini, $ini.'.bak-'.date('Ymd-His'));
}

$contents = file_get_contents($ini);
$extDir = $phpDir.DIRECTORY_SEPARATOR.'ext';

// Point extension_dir at PHP's own ext folder so the DLLs are found wherever setup runs from.
if (is_dir($extDir) && ! preg_match('/^\s*extension_dir\s*=/m', $contents)) {
    $contents .= PHP_EOL.'extension_dir = "'.$extDir.'"'.PHP_EOL;
}

foreach ($missing as $ext) {
    $pattern = '/^\s*;\s*(extension\s*=\s*(?:php_)?'.preg_quote($ext, '/').'(?:\.dll|\.so)?\s*)$/mi';

    if (preg_match($pattern, $contents)) {
        $contents = preg_replace($pattern, '$1', $contents);
    } elseif (is_file($extDir.DIRECTORY_SEPARATOR.'php_'.$ext.'.dll')) {
        $contents .= PHP_EOL.'extension='.$ext.PHP_EOL;
    } else {
        fwrite(STDOUT, "  {$ext}: no php_{$ext}.dll in {$extDir}; it has to be installed separately.".PHP_EOL);
        $ok = false;
    }
}

if (@file_put_contents($ini, $contents) === false) {
    fwrite(STDOUT, "Could not write {$ini}. Run setup.bat as administrator, or edit it yourself.".PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Updated {$ini}.".PHP_EOL);

exit($ok ? 0 : 1);
