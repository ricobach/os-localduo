<?php
/**
 * Enable, disable or inspect a single LocalDuo login-script reference in the
 * OPNsense 26.7 core login template. Runs through privileged configd actions.
 * Never overwrites the complete login form or injects PHP into it.
 */
$operation = $argv[1] ?? '';
$target = '/usr/local/www/authgui.inc';
$include = '    <script src="/ui/js/localduo-login.js"></script>';
$anchor = '    <script src="/ui/js/jquery-3.5.1.min.js"></script>';

function result(string $status, string $state, string $message): void
{
    echo json_encode(compact('status', 'state', 'message'), JSON_UNESCAPED_SLASHES) . "\n";
    exit($status === 'ok' ? 0 : 1);
}

if (!in_array($operation, ['status', 'enable', 'disable'], true)) {
    result('failed', 'unsupported', 'Unsupported login progress operation.');
}
if (!is_file($target) || !is_readable($target)) {
    result('failed', 'unsupported', 'OPNsense login template is unavailable.');
}
$content = file_get_contents($target);
if ($content === false) {
    result('failed', 'unsupported', 'Unable to read OPNsense login template.');
}
$position = strpos($content, 'function display_login_form(');
if ($position === false) {
    result('failed', 'unsupported', 'OPNsense login template format is unsupported.');
}
$head = substr($content, 0, $position);
$login = substr($content, $position);
$instances = substr_count($login, $include);
if ($instances > 1) {
    result('failed', 'unsupported', 'Duplicate login patch references detected; manual review required.');
}
$enabled = $instances === 1;
if ($operation === 'status') {
    result('ok', $enabled ? 'enabled' : 'disabled',
        $enabled ? 'Login progress is enabled.' : 'Login progress is disabled.');
}
if (($operation === 'enable' && $enabled) || ($operation === 'disable' && !$enabled)) {
    result('ok', $enabled ? 'enabled' : 'disabled', 'No change required.');
}
if (!is_writable($target)) {
    result('failed', $enabled ? 'enabled' : 'disabled', 'OPNsense login template is not writable.');
}
if ($operation === 'enable') {
    if (substr_count($login, $anchor) !== 1) {
        result('failed', 'unsupported', 'OPNsense login template changed; patch not applied.');
    }
    $login = str_replace($anchor, $anchor . "\n" . $include, $login);
} else {
    if (substr_count($login, "\n" . $include) !== 1) {
        result('failed', 'unsupported', 'Unable to safely identify the existing patch.');
    }
    $login = str_replace("\n" . $include, '', $login);
}
$updated = $head . $login;
$temp = tempnam(dirname($target), '.localduo-');
if ($temp === false) {
    result('failed', $enabled ? 'enabled' : 'disabled', 'Unable to stage the change.');
}
$ok = file_put_contents($temp, $updated) === strlen($updated);
$mode = fileperms($target);
$owner = fileowner($target);
$group = filegroup($target);
if ($mode !== false) {
    $ok = @chmod($temp, $mode & 07777) && $ok;
}
if ($owner !== false) {
    $ok = @chown($temp, $owner) && $ok;
}
if ($group !== false) {
    $ok = @chgrp($temp, $group) && $ok;
}
if (!$ok || !@rename($temp, $target)) {
    @unlink($temp);
    result('failed', $enabled ? 'enabled' : 'disabled', 'Unable to modify login template.');
}
result('ok', $operation === 'enable' ? 'enabled' : 'disabled',
    $operation === 'enable' ? 'Login progress enabled.' : 'Login progress disabled.');
