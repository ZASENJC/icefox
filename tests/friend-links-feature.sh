#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

require_pattern() {
    pattern=$1
    file=$2
    message=$3
    if ! rg -q -- "$pattern" "$file"; then
        echo "$message" >&2
        exit 1
    fi
}

test -f links-page.php || {
    echo 'the theme must provide an independent friend-links page template' >&2
    exit 1
}

test -f core/friend-links.php || {
    echo 'friend-link persistence must live in a reusable theme module' >&2
    exit 1
}

test -f assets/js/friend-links.js || {
    echo 'the page and modal must share one friend-link client' >&2
    exit 1
}

require_pattern "include_once 'core/friend-links.php'" functions.php 'the friend-link theme module must load with the theme'
require_pattern 'friendLinksPageUrl' functions.php 'the independent friend-links page URL must be configurable'
require_pattern 'template = \?' core/friend-links.php 'friend-link URL fallback must locate the published page template'
require_pattern 'Typecho_Router::url' core/friend-links.php 'friend-link URL fallback must use Typecho permalink rules'
require_pattern "'friendLinksUrl'" header.php 'the browser must receive the configured friend-links page URL'
require_pattern 'assets/js/friend-links\.js' header.php 'the shared friend-link client must load before Alpine initializes components'
require_pattern 'icefoxHandleFriendLinksRequest' links-page.php 'the independent page must own its read and write requests'
require_pattern 'table\.fields' core/friend-links.php 'friend links must be stored on the independent page instead of a plugin table'
require_pattern "friendLinks" core/friend-links.php 'the independent page field name must be explicit'
require_pattern 'hasLogin\(' core/friend-links.php 'only logged-in Typecho users may mutate friend links'
require_pattern 'getToken\(' core/friend-links.php 'friend-link writes must require a fresh Typecho security token'
require_pattern 'protect\(' core/friend-links.php 'friend-link writes must invoke Typecho CSRF protection'
require_pattern "components/modals/links.php" components/head.php 'the top friend-link control and its modal must be mounted together'
require_pattern 'icefoxFriendLinksManager' components/modals/links.php 'the modal must use the shared theme client'
require_pattern 'links-page-content' links-page.php 'the independent page must render the same friend-link data outside the modal'
require_pattern '(links-container|links-modal-header|links-field)' assets/css/icefox.css 'friend-link controls must have dedicated modal and form styles'

for template in links-page.php components/modals/links.php; do
    require_pattern 'class="link-address"[^>]*x-text="link\.url"' "$template" 'friend-link cards must show the site address below the site name'
    require_pattern 'class="link-description"[^>]*x-text="link\.description"' "$template" 'friend-link cards must place the description in its own right-side region'
done

if rg -n 'link-arrow' links-page.php components/modals/links.php assets/css/icefox.css; then
    echo 'friend-link cards must not render a trailing direction indicator' >&2
    exit 1
fi

if ! rg -q -U '(?s)\.links-container \{.*?max-width: 540px;.*?border-radius: 8px;' assets/css/icefox.css; then
    echo 'the friend-link modal must use the same compact 8px container language as settings and publishing' >&2
    exit 1
fi

if ! rg -q -U '(?s)\.links-modal-header \{.*?position: sticky;.*?border-bottom: 1px solid var\(--line\);' assets/css/icefox.css; then
    echo 'the friend-link modal header must match the established sticky divider layout' >&2
    exit 1
fi

if rg -n 'getFriendLinks|deleteFriendLink|icefox_links' assets/js/icefox-plugin.js plugins/Icefox/Action.php plugins/Icefox/Plugin.php; then
    echo 'friend-link runtime persistence must no longer depend on the companion plugin' >&2
    exit 1
fi

if rg -n "components/modals/links\.php" index.php archive.php archive-page.php page.php post.php album-page.php; then
    echo 'page templates must not mount duplicate friend-link modals' >&2
    exit 1
fi

test -f scripts/migrate-plugin-links.php || {
    echo 'existing plugin-owned friend links need a copy-only migration path' >&2
    exit 1
}

require_pattern 'hash.*sha256|SHA-256' scripts/migrate-plugin-links.php 'the migration must report a checksum for the copied JSON'
require_pattern 'source table.*not.*deleted|不会删除源表' scripts/migrate-plugin-links.php 'the migration must explicitly preserve the source table'

node tests/friend-links-client.js

echo 'Independent friend-links page and modal contracts verified'
