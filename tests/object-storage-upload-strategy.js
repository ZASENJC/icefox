const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/icefox-plugin.js', 'utf8');
const window = {
    ICEFOX_CONFIG: {
        actionUrl: '/action/icefox',
        uploadStorage: 'object',
        phpUploadMaxBytes: 20 * 1024 * 1024,
        phpPostMaxBytes: 128 * 1024 * 1024
    },
    location: { href: 'https://example.com/albums' },
    fetch: async () => ({ ok: true, json: async () => ({ success: true, token: 'token' }) })
};

vm.runInNewContext(source, { window, URL });

const shouldStage = window.ICEFOX_PLUGIN.shouldStageObjectFiles;
assert.equal(typeof shouldStage, 'function', 'the upload client must expose its fallback decision');
assert.equal(
    shouldStage([{ size: 5 * 1024 * 1024 }]),
    false,
    'an object image within the PHP request limits should use direct multipart upload'
);
assert.equal(
    shouldStage([{ size: 21 * 1024 * 1024 }]),
    true,
    'an image above upload_max_filesize should use chunk staging'
);
assert.equal(
    shouldStage(Array.from({ length: 13 }, () => ({ size: 10 * 1024 * 1024 }))),
    true,
    'a batch above post_max_size should use chunk staging'
);

window.ICEFOX_CONFIG.phpUploadMaxBytes = 0;
assert.equal(
    shouldStage([{ size: 1024 }]),
    true,
    'unknown PHP limits should retain chunk staging as the safe fallback'
);

console.log('Object storage upload strategy verified');
