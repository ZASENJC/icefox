#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

reject_pattern() {
    pattern=$1
    file=$2
    message=$3
    if rg -q -- "$pattern" "$file"; then
        echo "$message" >&2
        exit 1
    fi
}

reject_pattern 'static \$fieldCache' core/core.php 'article field caching must be rolled back'
reject_pattern 'static \$topCache' functions.php 'pinned-post caching must be rolled back'
reject_pattern 'function getPostContentView' functions.php 'shared timeline parsing must be rolled back'
reject_pattern 'function getArchiveTimelineMoments\([^)]' functions.php 'timeline pagination arguments must be rolled back'
reject_pattern 'moments-pagination' assets/css/icefox.css 'timeline pagination styles must be rolled back'
reject_pattern 'loading="lazy"' functions.php 'timeline image lazy loading must be rolled back'
reject_pattern 'htmlspecialchars' components/post/post-images.php 'feed image output changes must be rolled back'
reject_pattern 'htmlspecialchars' components/post/post-position.php 'position output changes must be rolled back'
reject_pattern 'htmlspecialchars' components/post/post-video.php 'video source output changes must be rolled back'

for asset in assets/css/normalize.css assets/js/axios.min.js; do
    if [ ! -f "$asset" ]; then
        echo "removed vendor asset must be restored: $asset" >&2
        exit 1
    fi
done

if ! rg -q '重新初始化Fancybox' assets/js/icefox.js; then
    echo 'infinite-scroll Fancybox initialization must be restored' >&2
    exit 1
fi

echo 'Rejected performance changes are fully rolled back'
