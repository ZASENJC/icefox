#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

if ! rg -q "Form_Element_Text\('beianUrl'" functions.php; then
    echo "the admin theme form must expose a configurable filing URL" >&2
    exit 1
fi

if ! rg -q "options->beianUrl" components/modals/setting.php; then
    echo "the settings footer must read the configured filing URL" >&2
    exit 1
fi

if ! rg -q 'https://beian\.miit\.gov\.cn/' components/modals/setting.php; then
    echo "the filing URL must retain a safe default" >&2
    exit 1
fi

if ! rg -q 'https://github\.com/ZASENJC/icefox' components/modals/setting.php; then
    echo "the theme credit must link to the fork repository" >&2
    exit 1
fi

if rg -q 'xiaopanglian\.com' components/modals/setting.php; then
    echo "the settings footer must not keep the upstream credit URL" >&2
    exit 1
fi

echo "Settings footer links are configurable and fork-backed"
