#!/bin/sh

set -eu

root_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
plugin_dir="$root_dir/plugins/IcefoxPlugin"

if [ ! -d "$plugin_dir" ] || [ -e "$root_dir/plugins/Icefox" ]; then
    echo 'the companion plugin source directory must be IcefoxPlugin only' >&2
    exit 1
fi

for file in Plugin.php Action.php AlbumArchive.php; do
    if ! rg -Fq 'namespace TypechoPlugin\IcefoxPlugin;' "$plugin_dir/$file"; then
        echo "$file must use the namespace matching the IcefoxPlugin directory" >&2
        exit 1
    fi
done

if ! rg -Fq '@package IcefoxPlugin' "$plugin_dir/Plugin.php" \
    || ! rg -Fq 'deactivate(self::LEGACY_PLUGIN_NAME)' "$plugin_dir/Plugin.php" \
    || ! rg -Fq 'plugin(self::LEGACY_PLUGIN_NAME)->toArray()' "$plugin_dir/Plugin.php" \
    || ! rg -Fq 'self::deactivate();' "$plugin_dir/Plugin.php"; then
    echo 'the renamed plugin entry must migrate legacy configuration and activation state' >&2
    exit 1
fi

if ! rg -Fq 'companion/IcefoxPlugin' "$root_dir/scripts/build-release.sh" \
    || ! rg -Fq 'Icefox-Plugin-$version.zip" IcefoxPlugin' "$root_dir/scripts/build-release.sh"; then
    echo 'the companion release archive must contain an IcefoxPlugin root directory' >&2
    exit 1
fi

echo 'Companion plugin directory migration contract verified'
