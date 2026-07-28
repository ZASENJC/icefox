#!/bin/sh

set -eu

storage_main=plugins/IcefoxStorage/Plugin.php
storage_client=plugins/IcefoxStorage/S3Client.php
storage_service=plugins/IcefoxStorage/StorageService.php
companion_action=plugins/Icefox/Action.php

for file in "$storage_main" "$storage_client" "$storage_service" "$companion_action"; do
    if [ ! -f "$file" ]; then
        echo "Required plugin file is missing: $file" >&2
        exit 1
    fi
done

require_pattern() {
    pattern=$1
    file=$2
    message=$3
    if ! rg -q -- "$pattern" "$file"; then
        echo "$message" >&2
        exit 1
    fi
}

for setting in provider endpoint region bucket accessKey secretKey publicUrl pathPrefix pathStyle; do
    require_pattern "'$setting'" "$storage_main" "IcefoxStorage config is missing $setting"
done

require_pattern 'ICEFOX_STORAGE_SECRET_KEY' "$storage_main" 'the secret key must support an environment override'
require_pattern 'configHandle' "$storage_main" 'the plugin must handle secrets without rendering a saved secret back into the form'
require_pattern 'hash_hmac' "$storage_client" 'the S3 client must implement AWS Signature V4'
require_pattern 'x-amz-content-sha256' "$storage_client" 'the S3 client must sign payload hashes'
require_pattern 'finfo' "$storage_service" 'uploaded images must be checked from their actual MIME content'
require_pattern 'image/svg' "$storage_service" 'SVG uploads must be explicitly rejected'
require_pattern 'objectKey' "$storage_service" 'stored metadata must retain the object key for deletion and migration'

require_pattern "request->get\('storage'" "$companion_action" 'the companion plugin must read the requested storage target'
require_pattern 'IcefoxStorage' "$companion_action" 'the companion plugin must call the standalone storage plugin'
require_pattern 'cleanupUploadedObjects' "$companion_action" 'failed database writes must clean up newly uploaded objects'
require_pattern 'saveAlbum' "$companion_action" 'album uploads must use the upgraded companion plugin'

echo 'Object storage plugin contracts are present'
