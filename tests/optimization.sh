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
assert_pattern '->limit\(\$pageSize\)' functions.php
assert_pattern 'function getPostContentView' functions.php

if ! rg -q "assets/js/icefox\.js" header.php; then
    echo "application scripts must remain in the established document order" >&2
    exit 1
fi

if [ -e assets/js/axios.min.js ] || [ -e assets/css/normalize.css ]; then
    echo "unused vendor assets remain" >&2
    exit 1
fi

echo "Optimization checks passed"
