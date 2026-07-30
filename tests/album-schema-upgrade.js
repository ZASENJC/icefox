const assert = require('node:assert/strict');
const fs = require('node:fs');

const pluginSource = fs.readFileSync('plugins/IcefoxPlugin/Plugin.php', 'utf8');
const actionSource = fs.readFileSync('plugins/IcefoxPlugin/Action.php', 'utf8');

const legacyTagMigration = pluginSource.match(
    /public static function migrateLegacyAlbumTags\(\)\s*\{([\s\S]*?)\n\s*\}\n\s*public static function getAlbumTagLinks/
);
assert.ok(legacyTagMigration, 'legacy album tag migration must be present');
assert.doesNotMatch(
    legacyTagMigration[1],
    /where\(['"]tags IS NOT NULL['"]\)/,
    'Typecho 1.2 query builder must not quote NOT as a column in raw IS NOT NULL conditions'
);
assert.match(
    legacyTagMigration[1],
    /where\(['"]tags <> \?['"],\s*['"]['"]\)/,
    'legacy album tag migration must exclude NULL and empty tags with a parameterized comparison'
);

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
