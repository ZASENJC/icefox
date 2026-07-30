<?php

/**
 * Backfill legacy comma-separated album tags into Typecho metas and the album join table.
 *
 * Usage:
 * TYPECHO_CONFIG=/absolute/path/to/config.inc.php \
 * ICEFOX_PLUGIN_MAIN=/absolute/path/to/usr/plugins/IcefoxPlugin/Plugin.php \
 * php scripts/migrate-album-tags.php
 */

$configPath = getenv('TYPECHO_CONFIG');
$pluginPath = getenv('ICEFOX_PLUGIN_MAIN');
if (!$configPath || !is_file($configPath)) {
    fwrite(STDERR, "TYPECHO_CONFIG must point to a readable Typecho config.inc.php\n");
    exit(2);
}
if (!$pluginPath || !is_file($pluginPath)) {
    fwrite(STDERR, "ICEFOX_PLUGIN_MAIN must point to the installed Icefox Plugin.php\n");
    exit(2);
}

require $configPath;
require_once $pluginPath;

try {
    \TypechoPlugin\Icefox\Plugin::ensureAlbumTagTableSchema();
    $result = \TypechoPlugin\Icefox\Plugin::migrateLegacyAlbumTags();
} catch (Exception $error) {
    fwrite(STDERR, "Album tag migration failed: {$error->getMessage()}\n");
    exit(1);
}

fwrite(
    STDOUT,
    "Migrated {$result['albums']} albums and added {$result['relationships']} album-tag relationships.\n"
);
