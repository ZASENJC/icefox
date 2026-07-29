<?php

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Return the number of albums visible to the current visitor for one standard tag.
 */
function icefoxGetVisibleTagAlbumCount($mid)
{
    $mid = (int) $mid;
    if ($mid <= 0) {
        return 0;
    }

    $pluginClass = '\\TypechoPlugin\\Icefox\\Plugin';
    if (!class_exists($pluginClass) || !is_callable([$pluginClass, 'getVisibleTagAlbumCount'])) {
        return 0;
    }

    try {
        $user = \Widget\User::alloc();
        return (int) $pluginClass::getVisibleTagAlbumCount($mid, $user->hasLogin());
    } catch (Exception $error) {
        error_log('Icefox album tag count query failed: ' . $error->getMessage());
        return 0;
    }
}

/**
 * Return visible albums related to the current standard Typecho tag archive.
 */
function icefoxGetTagArchiveAlbums($archive)
{
    if (!is_object($archive) || !$archive->is('tag')) {
        return array();
    }

    $slug = method_exists($archive, 'getArchiveSlug')
        ? trim((string) $archive->getArchiveSlug())
        : '';
    if ($slug === '') {
        return array();
    }

    $pluginClass = '\\TypechoPlugin\\Icefox\\Plugin';
    if (!class_exists($pluginClass) || !is_callable([$pluginClass, 'getTagArchiveAlbums'])) {
        return array();
    }

    try {
        $user = \Widget\User::alloc();
        $rows = $pluginClass::getTagArchiveAlbums($slug, $user->hasLogin());
    } catch (Exception $error) {
        error_log('Icefox album tag archive query failed: ' . $error->getMessage());
        return array();
    }

    $albums = array();
    foreach ($rows as $row) {
        $albums[] = array(
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'cover' => trim((string) ($row['cover'] ?? '')),
            'address' => (string) ($row['address'] ?? ''),
            'photoCount' => (int) ($row['photoCount'] ?? 0),
            'permalink' => Typecho_Common::url(
                'albums/' . rawurlencode((string) $row['slug']),
                $archive->options->index
            )
        );
    }

    return $albums;
}
