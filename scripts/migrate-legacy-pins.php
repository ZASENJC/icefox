<?php

/**
 * Copy legacy Icefox plugin pin values into the theme-owned Typecho isTop field.
 *
 * Usage:
 * TYPECHO_CONFIG=/absolute/path/to/config.inc.php php scripts/migrate-legacy-pins.php
 */

$configPath = getenv('TYPECHO_CONFIG');
if (!$configPath || !is_file($configPath)) {
    fwrite(STDERR, "TYPECHO_CONFIG must point to a readable Typecho config.inc.php\n");
    exit(2);
}

require $configPath;

$db = class_exists('Typecho\\Db') ? \Typecho\Db::get() : Typecho_Db::get();
$prefix = $db->getPrefix();
$migrated = 0;
$preserved = 0;
$missingPosts = 0;

try {
    $legacyRows = $db->fetchAll(
        $db->select('cid', 'is_top')
            ->from($prefix . 'icefox_archive')
            ->where('is_top = ?', 1)
    );
} catch (Exception $error) {
    fwrite(STDERR, "Unable to read the legacy icefox_archive table: {$error->getMessage()}\n");
    exit(1);
}

foreach ($legacyRows as $legacyRow) {
    $cid = (int) $legacyRow['cid'];
    if ($cid <= 0) {
        continue;
    }

    $post = $db->fetchRow(
        $db->select('cid')
            ->from('table.contents')
            ->where('cid = ?', $cid)
            ->where('type = ?', 'post')
    );
    if (!$post) {
        $missingPosts++;
        continue;
    }

    $existingField = $db->fetchRow(
        $db->select('cid')
            ->from('table.fields')
            ->where('cid = ?', $cid)
            ->where('name = ?', 'isTop')
    );
    if ($existingField) {
        $preserved++;
        continue;
    }

    $db->query($db->insert('table.fields')->rows(array(
        'cid' => $cid,
        'name' => 'isTop',
        'type' => 'int',
        'int_value' => 1
    )));
    $migrated++;
}

fwrite(STDOUT, "Migrated {$migrated} pinned posts; preserved {$preserved} existing choices; skipped {$missingPosts} missing posts.\n");
