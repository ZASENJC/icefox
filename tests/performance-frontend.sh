#!/bin/sh

set -u

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

failures=0

require_pattern() {
    pattern=$1
    file=$2
    message=$3
    if ! rg -q -- "$pattern" "$file"; then
        echo "$message" >&2
        failures=$((failures + 1))
    fi
}

reject_pattern() {
    pattern=$1
    file=$2
    message=$3
    if rg -q -- "$pattern" "$file"; then
        echo "$message" >&2
        failures=$((failures + 1))
    fi
}

require_pattern 'static \$fieldCache' core/core.php 'article fields must be cached per post'
require_pattern "getPostField\(.*'isTop'.*'int'" functions.php 'pinned-post lookups must use the cached Typecho field reader'
require_pattern 'function getPostContentView' functions.php 'timeline content parsing must be shared'
require_pattern '->limit\(\$pageSize\)' functions.php 'timeline queries must be paginated'
require_pattern 'moments-pagination' assets/css/icefox.css 'timeline pagination styles are missing'
require_pattern 'htmlspecialchars' components/post/post-images.php 'feed image URLs must be escaped'
require_pattern 'htmlspecialchars' components/post/post-position.php 'position output must be escaped'
require_pattern 'htmlspecialchars' components/post/post-video.php 'video source URLs must be escaped'

for asset in assets/css/normalize.css assets/js/axios.min.js; do
    if [ -e "$asset" ]; then
        echo "unused vendor asset must be removed: $asset" >&2
        failures=$((failures + 1))
    fi
done

fancybox_bind_count=$(rg -c 'Fancybox\.bind\(' assets/js/icefox.js || true)
if [ "$fancybox_bind_count" -ne 1 ]; then
    echo 'Fancybox must be bound once instead of after every infinite-scroll load' >&2
    failures=$((failures + 1))
fi

for script in icefox-plugin.js jquery.min.js alpinejs.js fancybox.umd.js scrollload.min.js music-player.js icefox.js; do
    require_pattern "<script defer src=.*assets/js/$script" header.php "deferred script loading is missing for $script"
done

reject_pattern '\$this->options->customJs\(\)' header.php 'custom JavaScript must not be emitted as raw script source'
require_pattern "DOMContentLoaded" header.php 'custom JavaScript must wait for deferred dependencies'
require_pattern 'new Function\(' header.php 'custom JavaScript syntax errors must be caught at runtime'
require_pattern 'JSON_HEX_TAG' header.php 'custom JavaScript must be safely encoded into the page'

if [ "$failures" -ne 0 ]; then
    echo "$failures performance/frontend checks failed" >&2
    exit 1
fi

echo 'Performance optimizations and frontend script guards are active'
