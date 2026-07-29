#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
action_file="$root_dir/plugins/Icefox/Action.php"
storage_plugin="$root_dir/plugins/IcefoxStorage/Plugin.php"
album_editor="$root_dir/components/modals/album-editor.php"

rg -q "'stageAlbumUpload'" "$action_file"
rg -q "private function stageAlbumUpload\(" "$action_file"
rg -q "php://input" "$action_file"
rg -q "consumeStagedAlbumUploads" "$action_file"
rg -q "public static function uploadPath\(" "$storage_plugin"
rg -q "stageObjectFiles" "$album_editor"
rg -q "stagedUploads" "$album_editor"

echo "album object chunk upload contract passed"
