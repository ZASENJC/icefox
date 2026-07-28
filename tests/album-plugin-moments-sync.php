<?php

$configPath = getenv('TYPECHO_CONFIG');
$actionPath = getenv('ICEFOX_PLUGIN_ACTION');

if (!$configPath || !$actionPath || !is_file($configPath) || !is_file($actionPath)) {
    fwrite(STDERR, "TYPECHO_CONFIG and ICEFOX_PLUGIN_ACTION must point to readable files\n");
    exit(2);
}

require $configPath;
require_once $actionPath;

$reflection = new ReflectionClass('TypechoPlugin\\Icefox\\Action');
if (!$reflection->hasMethod('extractPostImagePhotos')) {
    fwrite(STDERR, "Action::extractPostImagePhotos is missing\n");
    exit(1);
}

$action = $reflection->newInstanceWithoutConstructor();
$extract = $reflection->getMethod('extractPostImagePhotos');
$extract->setAccessible(true);

$content = <<<'MARKDOWN'
<!--markdown-->测试动态

![远程图片](https://img.example.com/remote.jpg "远程标题")
![本地图片](/usr/uploads/2026/07/local.webp)
![重复图片](https://img.example.com/remote.jpg)
<img src="https://img.example.com/from-html.png" alt="HTML 图片">
![危险图片](javascript:alert(1))
<img src="data:image/png;base64,AAAA" alt="内联图片">
MARKDOWN;

$actual = $extract->invoke($action, $content);
$expected = [
    ['src' => 'https://img.example.com/remote.jpg', 'alt' => '远程图片'],
    ['src' => '/usr/uploads/2026/07/local.webp', 'alt' => '本地图片'],
    ['src' => 'https://img.example.com/from-html.png', 'alt' => 'HTML 图片']
];

if ($actual !== $expected) {
    fwrite(STDERR, "Markdown image extraction mismatch\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
    exit(1);
}

echo "Album Markdown image extraction verified\n";

if (getenv('ICEFOX_DB_INTEGRATION') === '1') {
    $db = \Typecho\Db::get();
    $prefix = $db->getPrefix();
    $before = $db->fetchRow(
        $db->select()->from($prefix . 'icefox_albums')->where('is_moments = ?', 1)
    );
    if (!$before) {
        fwrite(STDERR, "Stable moments album is missing\n");
        exit(1);
    }

    $append = $reflection->getMethod('appendImagesToMomentsAlbum');
    $append->setAccessible(true);
    $integrationSrc = 'https://img.example.com/integration-moments-' . bin2hex(random_bytes(8)) . '.jpg';
    $integrationError = '';
    try {
        $count = $append->invoke(
            $action,
            [],
            '![集成图片](' . $integrationSrc . ')',
            (int) $before['created_by']
        );
        $during = $db->fetchRow(
            $db->select()->from($prefix . 'icefox_albums')->where('id = ?', (int) $before['id'])
        );
        $duringPhotos = json_decode((string) $during['photos'], true);
        $duringSources = array_map(function ($photo) {
            return is_array($photo) ? ($photo['src'] ?? '') : (string) $photo;
        }, is_array($duringPhotos) ? $duringPhotos : []);
        if ($count !== 1 || !in_array($integrationSrc, $duringSources, true)) {
            $integrationError = 'Markdown image was not written to the moments album; count=' . var_export($count, true)
                . '; sources=' . json_encode($duringSources, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    } finally {
        $db->query($db->update($prefix . 'icefox_albums')->rows([
            'slug' => $before['slug'],
            'cover' => $before['cover'],
            'is_moments' => $before['is_moments'],
            'photos' => $before['photos'],
            'updated_at' => $before['updated_at']
        ])->where('id = ?', (int) $before['id']));
    }

    if ($integrationError !== '') {
        fwrite(STDERR, $integrationError . "\n");
        exit(1);
    }

    $after = $db->fetchRow(
        $db->select()->from($prefix . 'icefox_albums')->where('id = ?', (int) $before['id'])
    );
    if ((string) $after['photos'] !== (string) $before['photos']) {
        fwrite(STDERR, "Moments integration test rollback failed\n");
        exit(1);
    }

    echo "Album moments database integration verified and restored\n";
}
