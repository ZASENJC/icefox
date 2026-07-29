#!/bin/sh

set -eu

plugin_main=plugins/Icefox/Plugin.php
plugin_action=plugins/Icefox/Action.php

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
require_pattern "Widget::widget\('Widget_Security'\)->protect\(\)" "$plugin_action" 'state-changing companion actions must enforce Typecho CSRF protection through the initialized security widget'
require_pattern 'postActions' "$plugin_action" 'state-changing companion actions must be explicitly classified as POST-only'
require_pattern 'in_array\(\$do, \$postActions, true\).*isPost' "$plugin_action" 'GET requests must not reach state-changing companion actions'
require_pattern "security\(\)->getIndex\('/action/icefox\?do=deleteFriendLink'" "$plugin_main" 'plugin admin actions must use a tokenized, rewrite-safe URL'
require_pattern 'FILTER_VALIDATE_URL' "$plugin_main" 'friend-link URLs must be validated before persistence'
require_pattern "\['http', 'https'\]" "$plugin_main" 'friend-link URLs must use an HTTP or HTTPS scheme'
require_pattern 'description.*normalizeText' "$plugin_main" 'existing friend-link descriptions must be normalized before persistence'

if rg -Fq "'description' => \$link['description'] ?? ''" "$plugin_main"; then
    echo 'existing friend-link descriptions must not be persisted without normalization' >&2
    exit 1
fi

if rg -n "fetch\([\"']/action/icefox" "$plugin_main"; then
    echo 'plugin admin JavaScript must not hardcode the Icefox action route' >&2
    exit 1
fi
if rg -n 'icefox_game_secret|WHERE cid = \{\$cid\}' "$plugin_action"; then
    echo 'the imported plugin must not retain hardcoded secrets or interpolated request IDs' >&2
    exit 1
fi

echo 'Companion plugin security contracts are present'
