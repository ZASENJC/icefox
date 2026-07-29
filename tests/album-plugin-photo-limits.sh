#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
action_file="$root_dir/plugins/Icefox/Action.php"

rg -q 'const ALBUM_PHOTO_LIMIT = 100;' "$action_file"
rg -q 'handleMediaUpload\(\$storageTarget, self::ALBUM_PHOTO_LIMIT, false\)' "$action_file"
rg -q 'count\(\$photos\) > self::ALBUM_PHOTO_LIMIT' "$action_file"
rg -q '朋友圈相册只支持从动态同步图片' "$action_file"
rg -q 'albumRequestHasManualPhotos' "$action_file"
rg -q 'appendImagesToMomentsAlbum' "$action_file"

echo "Album plugin photo limit contracts passed"
