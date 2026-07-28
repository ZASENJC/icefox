#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

if ! rg -Fq '<a href="<?php $this->permalink() ?>"><?php $this->author() ?></a>' components/post-list.php; then
    echo "the homepage author name must link to the current post" >&2
    exit 1
fi

detail_author_links=$(rg -Fc '<a href="<?php $this->author->permalink() ?>">' post.php)
if [ "$detail_author_links" -lt 2 ]; then
    echo "the post detail avatar and author name must link to the author page" >&2
    exit 1
fi

echo "Homepage and post-detail author links use their intended destinations"
