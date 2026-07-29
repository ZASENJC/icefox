#!/bin/sh

set -eu

runtime_files='header.php
edit-page.php
assets/js/icefox.js
components/album-gallery.php
components/modals/album-editor.php
components/modals/editor.php'

node tests/plugin-api.js

if rg -n '\?do=(getLikes|like|addComment|createPost|getAlbums|getAlbum|saveAlbum)' $runtime_files; then
    echo 'plugin actions must be routed through window.ICEFOX_PLUGIN' >&2
    exit 1
fi

if ! rg -q 'icefox-plugin\.js' header.php; then
    echo 'header.php must load the centralized plugin client' >&2
    exit 1
fi

if [ -e core/plugin-bridge.php ]; then
    echo 'runtime plugin database compatibility code must be removed' >&2
    exit 1
fi

if rg -n --glob '*.php' --glob '!scripts/**' --glob '!tests/**' --glob '!plugins/**' -- '->from\([^)]*icefox_' .; then
    echo 'theme runtime must not read plugin-owned tables' >&2
    exit 1
fi

if [ ! -f docs/plugin-boundaries.md ]; then
    echo 'the theme/plugin ownership guide is missing' >&2
    exit 1
fi

for heading in '主题直接实现' '主题界面 + 插件后端' '插件完全负责' '旧置顶数据迁移' '配套插件升级要求'; do
    if ! rg -Fq "$heading" docs/plugin-boundaries.md; then
        echo "ownership guide is missing section: $heading" >&2
        exit 1
    fi
done

plugin_main=${ICEFOX_PLUGIN_MAIN:-}
plugin_action=${ICEFOX_PLUGIN_ACTION:-}
if [ -n "$plugin_main" ] && [ -f "$plugin_main" ]; then
    if rg -n 'Widget(\\\\Archive|_Archive).*indexHandle|function indexHandle\(' "$plugin_main"; then
        echo 'the companion plugin must remove its old article-pinning query hook' >&2
        exit 1
    fi

    plugin_admin=$(dirname "$plugin_main")/admin/manage-posts.php
    if [ -f "$plugin_admin" ] && rg -n 'do=top|取消置顶' "$plugin_admin"; then
        echo 'the companion plugin must remove its old article-pinning admin button' >&2
        exit 1
    fi

    plugin_album_archive=$(dirname "$plugin_main")/AlbumArchive.php
    if [ ! -f "$plugin_album_archive" ]; then
        echo 'the companion plugin must provide the album page route target' >&2
        exit 1
    fi
    if ! rg -q "addRoute\\('icefox_albums'.*'/albums'" "$plugin_main" \
        || ! rg -q "addRoute\\('icefox_album_detail'.*'/albums/\\[album\\]'" "$plugin_main"; then
        echo 'the companion plugin must register album root and detail routes' >&2
        exit 1
    fi
    if ! rg -q "removeRoute\\('icefox_albums'" "$plugin_main" \
        || ! rg -q "removeRoute\\('icefox_album_detail'" "$plugin_main"; then
        echo 'the companion plugin must remove album routes when deactivated' >&2
        exit 1
    fi
fi

if [ -n "$plugin_action" ] && [ -f "$plugin_action" ]; then
    if rg -n "do *==? *['\"]top['\"]|function setTop\(" "$plugin_action"; then
        echo 'the companion plugin must remove its old article-pinning action' >&2
        exit 1
    fi
fi

echo 'Theme/plugin boundaries are explicit'
