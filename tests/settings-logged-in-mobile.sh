#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

if ! rg -q -U '(?s)@media \(max-width: 768px\).*?\.user-info-actions \{\s*flex-direction: row;' assets/css/icefox.css; then
    echo "logged-in account actions must remain on one mobile row" >&2
    exit 1
fi

if ! rg -q -U '(?s)@media \(max-width: 576px\).*?\.user-info-details \{\s*grid-template-columns: repeat\(2, minmax\(0, 1fr\)\);' assets/css/icefox.css; then
    echo "nickname and role must share the first mobile row" >&2
    exit 1
fi

if ! rg -q -U '(?s)\.user-info-item-email \{.*?grid-column: 1 / -1;' assets/css/icefox.css; then
    echo "email must use its own mobile row" >&2
    exit 1
fi

if ! rg -q -U '(?s)\.setting-mode-button \{.*?background-color: #07C160;' assets/css/icefox.css; then
    echo "the desktop theme mode button must be visually distinct" >&2
    exit 1
fi

echo "Logged-in mobile account layout stays compact"
