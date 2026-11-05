<?php
/**
 * Idempotently add/remove one script include in OPNsense's native login page.
 *
 * OPNsense 26.7 does not provide a plugin hook for the unauthenticated Web GUI
 * login template. Never overwrite the core file: update exactly one known
 * anchor in display_login_form(), and do nothing if its structure changes.
 */
$operation = $argv[1] ?? '';
$target = $argv[2] ?? '/usr/local/www/authgui.inc';
if (!in_array($operation, ['install', 'remove'], true)) {
    fwrite(STDERR, "LocalDuo: expected install or remove\n");
    exit(1);
}
if (!is_file($target) || !is_readable($target) || !is_writable($target)) {
    fwrite(STDERR, "LocalDuo: login template missing or unwritable; progress hint not installed\n");
    exit(0);
}
$source = file_get_contents($target);
if ($source === false) {
    fwrite(STDERR, "LocalDuo: cannot read login template\n");
    exit(0);
}
$function = 'function display_login_form(';
$position = strpos($source, $function);
if ($position === false) {
    fwrite(STDERR, "LocalDuo: unknown login template; leaving it unchanged\n");
    exit(0);
}
$head = substr($source, 0, $position);
$login = substr($source, $position);
$anchor = '    <script src="/ui/js/jquery-3.5.1.min.js"></script>';
$include = '    <script src="/ui/js/localduo-login.js"></script>';

if ($operation === 'install') {
    if (strpos($login, $include) !== false) {
        exit(0);
    }
    if (substr_count($login, $anchor) !== 1) {
        fwrite(STDERR, "LocalDuo: login template anchor changed; leaving it unchanged\n");
        exit(0);
    }
    $login = str_replace($anchor, $anchor . "\n" . $include, $login);
} else {
    if (strpos($login, $include) === false) {
        exit(0);
    }
    $login = str_replace("\n" . $include, '', $login);
}
$updated = $head . $login;
$temp = tempnam(dirname($target), '.localduo-');
if ($temp === false) {
    fwrite(STDERR, "LocalDuo: unable to stage login template update\n");
    exit(0);
}
$ok = file_put_contents($temp, $updated) === strlen($updated);
$mode = fileperms($target);
if ($mode !== false) {
    $ok = @chmod($temp, $mode & 07777) && $ok;
}
$owner = fileowner($target);
$group = filegroup($target);
if ($owner !== false) {
    $ok = @chown($temp, $owner) && $ok;
}
if ($group !== false) {
    $ok = @chgrp($temp, $group) && $ok;
}
if (!$ok || !@rename($temp, $target)) {
    @unlink($temp);
    fwrite(STDERR, "LocalDuo: could not update login template; original left unchanged\n");
    exit(0);
}
fwrite(STDOUT, "LocalDuo: login progress script " . ($operation === 'install' ? 'enabled' : 'removed') . "\n");
