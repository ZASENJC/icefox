#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

if rg -q 'scheduleLikeData\(' assets/js/icefox.js; then
    echo "like data must load immediately without viewport scheduling" >&2
    exit 1
fi

direct_like_loads=$(rg -c 'loadLikeData\(cid, \$likeContainer\)' assets/js/icefox.js)
if [ "$direct_like_loads" -lt 2 ]; then
    echo "initial and infinite-scroll posts must both load like data directly" >&2
    exit 1
fi

if ! rg -q 'isCommentRelatedToTopComment\(\$childComment\['"'"'coid'"'"'\], \$topComment\['"'"'coid'"'"'\], \$commentMap\)' comment_function.php; then
    echo "comment replies must use the established top-level association path" >&2
    exit 1
fi

echo "Feed comments and likes use the established loading path"
