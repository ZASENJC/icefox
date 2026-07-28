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

echo 'Post pinning is theme-owned and migration-ready'
