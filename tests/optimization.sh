#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

assert_pattern() {
    pattern=$1
    file=$2
    if ! rg -q -- "$pattern" "$file"; then
        echo "missing optimization pattern '$pattern' in $file" >&2
        exit 1
    fi
}

assert_pattern 'static \$fieldCache' core/core.php
assert_pattern 'childrenByParent' comment_function.php
assert_pattern 'function renderPostFeedContent' functions.php
assert_pattern '->limit\(\$pageSize\)' functions.php
assert_pattern 'loading="lazy"' components/post/post-images.php
assert_pattern 'assets/js/icefox.js' footer.php

if rg -n '<script src=.*assets/js' header.php; then
    echo "application scripts must not block the document head" >&2
    exit 1
fi

if rg -n 'generateContentWithSummaryAndMusic\(' components/post-list.php archive.php; then
    echo "feed templates must use the shared post renderer" >&2
    exit 1
fi

if [ -e assets/js/axios.min.js ] || [ -e assets/css/normalize.css ]; then
    echo "unused vendor assets remain" >&2
    exit 1
fi

echo "Optimization checks passed"
