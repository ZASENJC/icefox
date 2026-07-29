#!/bin/sh

set -eu

plugin_main=plugins/Icefox/Plugin.php
plugin_action=plugins/Icefox/Action.php
friend_links_module=core/friend-links.php

require_pattern() {
    pattern=$1
    file=$2
    message=$3
    if ! rg -q -- "$pattern" "$file"; then
        echo "$message" >&2
        exit 1
    fi
}

require_pattern 'security\(\)->getIndex' header.php 'frontend plugin requests must use a Typecho CSRF token URL'
require_pattern '\$security->protect\(\)' "$plugin_action" 'state-changing companion actions must enforce Typecho CSRF protection through the initialized security widget'
require_pattern 'postActions' "$plugin_action" 'state-changing companion actions must be explicitly classified as POST-only'
require_pattern 'in_array\(\$do, \$postActions, true\).*isPost' "$plugin_action" 'GET requests must not reach state-changing companion actions'
require_pattern 'FILTER_VALIDATE_URL' "$friend_links_module" 'friend-link URLs must be validated before persistence'
require_pattern "\['http', 'https'\]" "$friend_links_module" 'friend-link URLs must use an HTTP or HTTPS scheme'
require_pattern 'description.*NormalizeText|description.*normalizeText' "$friend_links_module" 'friend-link descriptions must be normalized before persistence'
require_pattern 'hasLogin\(' "$friend_links_module" 'friend-link writes must require a Typecho login'
require_pattern 'protect\(\)' "$friend_links_module" 'friend-link writes must enforce Typecho CSRF protection'

if rg -n "fetch\([\"']/action/icefox" "$plugin_main"; then
    echo 'plugin admin JavaScript must not hardcode the Icefox action route' >&2
    exit 1
fi
if rg -n 'icefox_game_secret|WHERE cid = \{\$cid\}' "$plugin_action"; then
    echo 'the imported plugin must not retain hardcoded secrets or interpolated request IDs' >&2
    exit 1
fi

echo 'Companion plugin security contracts are present'
