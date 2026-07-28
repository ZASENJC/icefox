#!/bin/sh

set -eu

require_pattern() {
    pattern=$1
    file=$2
    message=$3
    if ! rg -q -- "$pattern" "$file"; then
        echo "$message" >&2
        exit 1
    fi
}

require_pattern "Form_Element_Radio\('isTop'" functions.php 'the post editor must expose an isTop field'
require_pattern '^function getPostIsTop\(' functions.php 'the theme must own pinned-post lookup'
require_pattern "getPostField\(.*'isTop'.*'int'" functions.php 'pinned-post lookup must read the Typecho isTop field'
require_pattern "include_once 'core/post-pinning.php'" functions.php 'the theme must load its pinned-post ordering module'

if [ ! -f core/post-pinning.php ]; then
    echo 'the theme-owned pinned-post ordering module is missing' >&2
    exit 1
fi

require_pattern '^function icefoxShouldOrderPinnedPosts\(' core/post-pinning.php 'pinned-post ordering must be scoped to article lists'
require_pattern '^function icefoxRegisterPinnedPostOrdering\(' core/post-pinning.php 'pinned-post ordering must register before the archive query runs'
require_pattern '^function icefoxApplyPinnedPostOrdering\(' core/post-pinning.php 'pinned-post ordering must modify the Typecho archive query'
require_pattern "join\('table\.fields AS icefox_pin'" core/post-pinning.php 'pinned-post ordering must use the Typecho fields table'
require_pattern "cleanAttribute\('order'\)" core/post-pinning.php 'pinned-post ordering must replace legacy and default ordering'
require_pattern "order\('icefox_pin\.cid IS NULL'" core/post-pinning.php 'pinned posts must sort before normal posts'
require_pattern "order\('table\.contents\.created'" core/post-pinning.php 'posts within each pin group must sort by creation time'
require_pattern "order\('table\.contents\.cid'" core/post-pinning.php 'post ordering must have a stable pagination tie-breaker'

if [ -e core/plugin-bridge.php ]; then
    echo 'the legacy runtime plugin bridge must be removed' >&2
    exit 1
fi

if rg -n 'icefox_archive|is_top' functions.php core components assets/js --glob '*.php' --glob '*.js'; then
    echo 'theme runtime must not read plugin-owned pinning data' >&2
    exit 1
fi

if rg -q "insert\('table\.fields'\)" functions.php; then
    echo 'theme activation must not create field rows without a post id' >&2
    exit 1
fi

if [ ! -f scripts/migrate-legacy-pins.php ]; then
    echo 'the one-time legacy pin migration script is missing' >&2
    exit 1
fi

require_pattern 'TYPECHO_CONFIG' scripts/migrate-legacy-pins.php 'migration must require an explicit Typecho config path'
require_pattern 'icefox_archive' scripts/migrate-legacy-pins.php 'migration must read the legacy pin source'
require_pattern "name.*isTop" scripts/migrate-legacy-pins.php 'migration must write the isTop field'
require_pattern 'existingField' scripts/migrate-legacy-pins.php 'migration must preserve an existing isTop choice'
require_pattern '旧置顶数据迁移' docs/plugin-boundaries.md 'the ownership guide must document legacy pin migration'

if command -v php >/dev/null 2>&1; then
    php tests/post-pinning-query.php
else
    echo 'PHP runtime query test skipped: php is unavailable'
fi

echo 'Post pinning is theme-owned and migration-ready'
