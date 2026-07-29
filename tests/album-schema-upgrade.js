const assert = require('node:assert/strict');
const fs = require('node:fs');

const pluginSource = fs.readFileSync('plugins/Icefox/Plugin.php', 'utf8');
const actionSource = fs.readFileSync('plugins/Icefox/Action.php', 'utf8');

assert.match(
    pluginSource,
    /public static function ensureAlbumTableSchema\s*\(/,
    'the companion plugin must expose its album schema migration to active installations'
);

const saveAlbum = actionSource.match(
    /private function saveAlbum\(\)\s*\{([\s\S]*?)\n\s*private function stageAlbumUpload\(/
);
assert.ok(saveAlbum, 'saveAlbum implementation must be present');

const migrationCall = saveAlbum[1].indexOf('Plugin::ensureAlbumTableSchema();');
const firstAlbumRead = saveAlbum[1].indexOf('$this->findAlbum(');
assert.ok(migrationCall >= 0, 'saving an album must upgrade the schema for already-active plugins');
assert.ok(firstAlbumRead >= 0, 'saveAlbum must read the existing album when editing');
assert.ok(
    migrationCall < firstAlbumRead,
    'the album schema must be upgraded before saveAlbum reads or writes album records'
);

console.log('Active plugin album schema upgrades are enforced before saves');
