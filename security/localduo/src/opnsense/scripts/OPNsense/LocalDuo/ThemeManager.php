<?php
/**
 * Manual LocalDuo theme management. Never runs during installation.
 * Copies the selected theme, adds a JS loader, and can switch back.
 */
require_once 'config.inc';

$operation = $argv[1] ?? '';
$root = '/usr/local/opnsense/www/themes';
$marker = '/* LOCALDUO MANAGED THEME */';
$metadata = '.localduo-source.json';
global $config;

function respond($ok, $state, $message, $extra = [])
{
    echo json_encode(array_merge(['status' => $ok ? 'ok' : 'failed', 'state' => $state, 'message' => $message], $extra), JSON_UNESCAPED_SLASHES) . "\n";
    exit($ok ? 0 : 1);
}
function validName($name) { return is_string($name) && preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $name); }
function selectedTheme()
{
    global $config;
    return (string)($config['system']['webgui']['theme'] ?? 'opnsense');
}
function managed($path)
{
    return is_file($path . '/.localduo-source.json') &&
        is_file($path . '/js/theme.js') &&
        strpos((string)file_get_contents($path . '/js/theme.js'), '/* LOCALDUO MANAGED THEME */') !== false;
}
function copyTheme($source, $destination)
{
    if (!mkdir($destination, 0755)) { throw new RuntimeException('Unable to create theme directory'); }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $entry) {
        // Do not follow symlinks: reject to prevent copying unexpected paths.
        if ($entry->isLink()) { throw new RuntimeException('Theme contains a symlink; cannot safely copy'); }
        $relative = substr($entry->getPathname(), strlen($source) + 1);
        $target = $destination . '/' . $relative;
        if ($entry->isDir()) {
            if (!mkdir($target, $entry->getPerms() & 0777)) { throw new RuntimeException('Cannot create theme subdirectory'); }
        } elseif ($entry->isFile()) {
            if (!copy($entry->getPathname(), $target)) { throw new RuntimeException('Cannot copy theme file'); }
        } else {
            throw new RuntimeException('Unsupported theme entry');
        }
    }
}
function removeTheme($path)
{
    if (!is_dir($path) || is_link($path)) { return; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $entry) {
        if ($entry->isDir() && !$entry->isLink()) { rmdir($entry->getPathname()); }
        else { unlink($entry->getPathname()); }
    }
    rmdir($path);
}
if (!in_array($operation, ['status', 'create', 'activate', 'restore'], true)) {
    respond(false, 'unsupported', 'Unsupported theme operation');
}
if (!is_dir($root) || !is_readable($root)) {
    respond(false, 'unsupported', 'OPNsense theme directory not found');
}
$current = selectedTheme();
if (!validName($current)) { respond(false, 'unsupported', 'Invalid selected theme name'); }
$currentPath = $root . '/' . $current;
if (!is_dir($currentPath) || is_link($currentPath)) { respond(false, 'unsupported', 'Selected theme directory unavailable'); }
$isManaged = managed($currentPath);
$source = $isManaged ? json_decode((string)file_get_contents($currentPath . '/' . $metadata), true)['source'] ?? '' : $current;
if (!validName($source)) { respond(false, 'unsupported', 'Invalid original theme'); }
$destinationName = $source . '-localduo';
$destination = $root . '/' . $destinationName;
if ($operation === 'status') {
    respond(true, $isManaged ? 'active' : (managed($destination) ? 'created' : 'absent'), 'Theme status retrieved', [
        'current' => $current, 'source' => $source, 'generated' => $destinationName
    ]);
}
if ($operation === 'create') {
    if ($isManaged) { respond(true, 'active', 'Duo theme already active'); }
    if (file_exists($destination)) {
        if (!managed($destination)) { respond(false, 'unsupported', 'Destination exists and is not managed by LocalDuo'); }
        respond(true, 'created', 'Duo theme already exists; activate it separately', ['generated' => $destinationName]);
    }
    $sourcePath = $root . '/' . $source;
    if (!is_dir($sourcePath) || is_link($sourcePath)) { respond(false, 'unsupported', 'Original theme not available'); }
    try {
        copyTheme($sourcePath, $destination);
        if (!is_dir($destination . '/js') && !mkdir($destination . '/js', 0755)) { throw new RuntimeException('Cannot create js directory'); }
        $js = $destination . '/js/theme.js';
        $loader = "\n" . $marker . "\n" .
            "(function(){if(document.querySelector('script[data-localduo-theme-loader]'))return;" .
            "var s=document.createElement('script');s.src='/ui/js/localduo-login.js';" .
            "s.setAttribute('data-localduo-theme-loader','1');document.head.appendChild(s);})();\n";
        if (file_put_contents($js, $loader, FILE_APPEND | LOCK_EX) === false ||
            file_put_contents($destination . '/' . $metadata, json_encode(['source' => $source]), LOCK_EX) === false) {
            throw new RuntimeException('Unable to write theme loader');
        }
    } catch (Throwable $e) {
        removeTheme($destination);
        respond(false, 'unsupported', 'Theme copy failed: ' . $e->getMessage());
    }
    respond(true, 'created', 'Duo theme created. Activate it separately.', ['generated' => $destinationName]);
}
if ($operation === 'activate') {
    if (!managed($destination)) { respond(false, 'unsupported', 'Create the Duo theme first'); }
    if ($isManaged) { respond(true, 'active', 'Duo theme already active'); }
    $config['system']['webgui']['theme'] = $destinationName;
    write_config('LocalDuo: activate copied theme');
    respond(true, 'active', 'Duo theme activated', ['source' => $source]);
}
if (!$isManaged) { respond(true, 'restored', 'Original theme already selected'); }
if (!is_dir($root . '/' . $source) || is_link($root . '/' . $source)) {
    respond(false, 'unsupported', 'Original theme is unavailable');
}
$config['system']['webgui']['theme'] = $source;
write_config('LocalDuo: restore original theme');
respond(true, 'restored', 'Original theme restored', ['source' => $source]);
