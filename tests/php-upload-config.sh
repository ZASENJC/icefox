#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
config_file="$root_dir/deploy/php-uploads.ini"

test -f "$config_file"
rg -q '^upload_max_filesize\s*=\s*20M$' "$config_file"
rg -q '^post_max_size\s*=\s*128M$' "$config_file"
rg -q '^max_file_uploads\s*=\s*40$' "$config_file"
rg -q 'deploy/php-uploads\.ini' "$root_dir/README.md"
rg -q '分片.*备用|备用.*分片' "$root_dir/plugins/IcefoxStorage/README.md"

echo "PHP upload configuration contract passed"
