<?php

/**
 * Copy Icefox plugin friend links into a theme-owned independent page field.
 * The source table is not deleted or modified.
 *
 * Usage:
 * TYPECHO_CONFIG=/absolute/path/to/config.inc.php \
 * FRIEND_LINKS_PAGE_CID=123 php scripts/migrate-plugin-links.php
 *
 * Set ICEFOX_OVERWRITE_LINKS=1 only when an existing target field should be replaced.
 */

$configPath = getenv('TYPECHO_CONFIG');
if (!$configPath || !is_file($configPath)) {
    fwrite(STDERR, "TYPECHO_CONFIG must point to a readable Typecho config.inc.php\n");
    exit(2);
}

$pageCid = (int) getenv('FRIEND_LINKS_PAGE_CID');
if ($pageCid <= 0) {
    fwrite(STDERR, "FRIEND_LINKS_PAGE_CID must be the numeric cid of the independent friend-links page\n");
    exit(2);
}

require $configPath;

$db = class_exists('Typecho\\Db') ? \Typecho\Db::get() : Typecho_Db::get();
$prefix = $db->getPrefix();
$page = $db->fetchRow(
    $db->select('cid', 'title')
        ->from('table.contents')
        ->where('cid = ?', $pageCid)
        ->where('type = ?', 'page')
        ->limit(1)
);
if (!$page) {
    fwrite(STDERR, "The target friend-links page does not exist\n");
    exit(1);
}

$existingField = $db->fetchRow(
    $db->select('str_value')
        ->from('table.fields')
        ->where('cid = ?', $pageCid)
        ->where('name = ?', 'friendLinks')
        ->limit(1)
);
if ($existingField && trim((string) $existingField['str_value']) !== '' && getenv('ICEFOX_OVERWRITE_LINKS') !== '1') {
    fwrite(STDERR, "The target page already has friendLinks data; set ICEFOX_OVERWRITE_LINKS=1 to replace it\n");
    exit(1);
}

try {
    $legacyLinks = $db->fetchAll(
        $db->select('id', 'name', 'url', 'avatar', 'description', 'sort')
            ->from($prefix . 'icefox_links')
            ->where('status = ?', 1)
            ->order('sort', class_exists('Typecho\\Db') ? \Typecho\Db::SORT_ASC : Typecho_Db::SORT_ASC)
    );
} catch (Exception $error) {
    fwrite(STDERR, "Unable to read the legacy icefox_links table: {$error->getMessage()}\n");
    exit(1);
}

$normalizeText = function ($value, $length) {
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', trim((string) $value));
    return mb_substr($value, 0, $length, 'UTF-8');
};
$normalizeUrl = function ($value, $allowEmpty = false) {
    $value = trim((string) $value);
    if ($value === '' && $allowEmpty) {
        return '';
    }
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        return null;
    }
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $value : null;
};

$links = [];
$skipped = 0;
foreach ($legacyLinks as $legacyLink) {
    $name = $normalizeText($legacyLink['name'] ?? '', 100);
    $url = $normalizeUrl($legacyLink['url'] ?? '');
    if ($name === '' || $url === null) {
        $skipped++;
        continue;
    }

    $avatar = $normalizeUrl($legacyLink['avatar'] ?? '', true);
    $links[] = [
        'id' => 'legacy-link-' . (int) $legacyLink['id'],
        'name' => $name,
        'url' => $url,
        'avatar' => $avatar === null ? '' : $avatar,
        'description' => $normalizeText($legacyLink['description'] ?? '', 200),
        'sort' => max(0, (int) ($legacyLink['sort'] ?? 0))
    ];
}

$json = json_encode([
    'version' => 1,
    'links' => $links
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, "Unable to encode migrated friend links\n");
    exit(1);
}
if (strlen($json) > 60000) {
    fwrite(STDERR, "Migrated friend-link JSON exceeds the Typecho field size; shorten the source records before retrying\n");
    exit(1);
}

$fieldData = [
    'type' => 'str',
    'str_value' => $json,
    'int_value' => 0,
    'float_value' => 0
];
if ($existingField) {
    $db->query(
        $db->update('table.fields')
            ->rows($fieldData)
            ->where('cid = ?', $pageCid)
            ->where('name = ?', 'friendLinks')
    );
} else {
    $fieldData['cid'] = $pageCid;
    $fieldData['name'] = 'friendLinks';
    $db->query($db->insert('table.fields')->rows($fieldData));
}

$writtenField = $db->fetchRow(
    $db->select('str_value')
        ->from('table.fields')
        ->where('cid = ?', $pageCid)
        ->where('name = ?', 'friendLinks')
        ->limit(1)
);
$sourceChecksum = hash('sha256', $json);
$writtenChecksum = hash('sha256', (string) ($writtenField['str_value'] ?? ''));
if (!hash_equals($sourceChecksum, $writtenChecksum)) {
    fwrite(STDERR, "Friend-link verification failed: target checksum does not match\n");
    exit(1);
}

fwrite(
    STDOUT,
    "Copied " . count($links) . " friend links to page {$pageCid}; skipped {$skipped} invalid records; SHA-256 {$writtenChecksum}. The source table was not deleted.\n"
);
