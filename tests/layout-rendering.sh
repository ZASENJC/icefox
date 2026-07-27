#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

if rg -q 'renderPostFeedContent\(' components/post-list.php archive.php; then
    echo "feed templates must retain their original rendering path" >&2
    exit 1
fi

if rg -q 'loading="lazy"' components/post/post-images.php; then
    echo "feed image markup must not defer image dimensions without reserved space" >&2
    exit 1
fi

if ! rg -q "assets/js/jquery\.min\.js" header.php; then
    echo "header.php must load frontend dependencies in the established order" >&2
    exit 1
fi

if rg -q "assets/js/jquery\.min\.js" footer.php; then
    echo "footer.php must not move frontend dependencies after Alpine markup" >&2
    exit 1
fi

echo "Layout rendering path is restored"
