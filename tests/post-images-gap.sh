#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

if ! rg -q -U '(?s)\.post-images \.grid \{.*?gap: 2px;' assets/css/icefox.css; then
    echo "post image grids must use a 2px gap" >&2
    exit 1
fi

if ! rg -q -U '(?s)@media \(max-width: 768px\) \{.*?\.fancybox__container \{.*?--f-carousel-gap: 2px;' assets/css/icefox.css; then
    echo "mobile image slides must use a 2px gap" >&2
    exit 1
fi

echo "Post image grids and mobile slides use a thin 2px gap"
