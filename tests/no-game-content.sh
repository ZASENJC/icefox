#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

if [ -e game-page.php ]; then
    echo "game-page.php must be removed from the theme" >&2
    exit 1
fi

if rg -n 'saveGameScore|getGameLeaderboard|icefox_game_secret_key' \
    --glob '*.php' \
    --glob '*.js' \
    --glob '*.css' \
    .; then
    echo "game API or signing code remains in theme runtime files" >&2
    exit 1
fi

if rg -n '游戏页面|小游戏功能' README.md; then
    echo "README still advertises the removed game page" >&2
    exit 1
fi

echo "No game content remains in the theme"
