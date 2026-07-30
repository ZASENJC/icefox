#!/bin/sh

set -eu

version="${1:-3.1.0}"
if ! printf '%s\n' "$version" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$'; then
    echo "Version must use the form X.Y.Z" >&2
    exit 2
fi

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
repo_dir=$(dirname "$script_dir")
output_dir="$repo_dir/release/$version"
stage_dir=$(mktemp -d "${TMPDIR:-/tmp}/icefox-release.XXXXXX")
theme_stage="$stage_dir/theme/icefox"
companion_stage="$stage_dir/companion/Icefox"
storage_stage="$stage_dir/storage/IcefoxStorage"

cleanup() {
    rm -r "$stage_dir"
}
trap cleanup EXIT HUP INT TERM

require_version() {
    file=$1
    if ! grep -Fq "@version $version" "$repo_dir/$file"; then
        echo "Version mismatch: $file does not declare @version $version" >&2
        exit 1
    fi
}

require_version index.php
require_version functions.php
require_version page.php
require_version post.php
require_version assets/js/music-player.js
require_version plugins/Icefox/Plugin.php
require_version plugins/IcefoxStorage/Plugin.php

if [ -d "$output_dir" ]; then
    rm -r "$output_dir"
fi
mkdir -p "$output_dir" "$theme_stage" "$companion_stage" "$storage_stage"

for file in \
    album-page.php archive-page.php archive.php comment_function.php edit-page.php \
    footer.php functions.php header.php index.php links-page.php page.php post.php \
    sidebar.php README.md RELEASE_NOTES.md screenshot.png
do
    cp "$repo_dir/$file" "$theme_stage/$file"
done

for directory in assets components core deploy docs
do
    cp -R "$repo_dir/$directory" "$theme_stage/$directory"
done

mkdir -p "$theme_stage/scripts"
cp "$repo_dir"/scripts/migrate-*.php "$theme_stage/scripts/"

cp -R "$repo_dir/plugins/Icefox/." "$companion_stage/"
cp -R "$repo_dir/plugins/IcefoxStorage/." "$storage_stage/"

find "$stage_dir" -name '.DS_Store' -delete
find "$stage_dir" -name '*.log' -delete

(
    cd "$stage_dir/theme"
    zip -X -q -r "$output_dir/icefox-$version.zip" icefox

    cd "$stage_dir/companion"
    zip -X -q -r "$output_dir/Icefox-Plugin-$version.zip" Icefox

    cd "$stage_dir/storage"
    zip -X -q -r "$output_dir/IcefoxStorage-$version.zip" IcefoxStorage
)

(
    cd "$output_dir"
    shasum -a 256 ./*.zip > SHA256SUMS
)

echo "Release artifacts written to $output_dir"
