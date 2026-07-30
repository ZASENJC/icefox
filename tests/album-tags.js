const assert = require('node:assert/strict');
const fs = require('node:fs');

const pluginSource = fs.readFileSync('plugins/IcefoxPlugin/Plugin.php', 'utf8');
const actionSource = fs.readFileSync('plugins/IcefoxPlugin/Action.php', 'utf8');
const gallerySource = fs.readFileSync('components/album-gallery.php', 'utf8');
const archiveSource = fs.readFileSync('archive.php', 'utf8');
const functionsSource = fs.readFileSync('functions.php', 'utf8');
const stylesheet = fs.readFileSync('assets/css/icefox.css', 'utf8');

assert.match(
    pluginSource,
    /CREATE TABLE IF NOT EXISTS[^;]*icefox_album_tags[\s\S]*?`album_id`[\s\S]*?`mid`[\s\S]*?PRIMARY KEY \(`album_id`, `mid`\)/,
    'the plugin must define an album-to-Typecho-tag relationship table'
);
assert.match(
    pluginSource,
    /function syncAlbumTags[\s\S]*?Metas::alloc\(\)->scanTags\([\s\S]*?table\.icefox_album_tags/,
    'album saves must reuse Typecho tag metas before writing album relationships'
);
assert.match(
    pluginSource,
    /function migrateLegacyAlbumTags[\s\S]*?syncAlbumTags/,
    'legacy comma-separated album tags must have an idempotent backfill path'
);
assert.match(
    pluginSource,
    /function getTagArchiveAlbums[\s\S]*?table\.icefox_album_tags/,
    'the companion plugin must own album queries for Typecho tag archives'
);
assert.match(
    pluginSource,
    /function getAlbumTagLinks[\s\S]*?array_diff\(\$legacyNames, \$linkedNames\)[\s\S]*?syncAlbumTags\(\(int\) \$albumId, \$legacyNames, false\)[\s\S]*?fetchAlbumTagRows/,
    'legacy album tags must be self-healed into Typecho tag relationships before rendering links'
);
assert.match(
    pluginSource,
    /Router::url\('tag',\s*\$row,\s*\\Widget\\Options::alloc\(\)->index\)/,
    'album tag links must use the standard Typecho router on both Typecho 1.2 and 1.3'
);
assert.match(
    actionSource,
    /Plugin::syncAlbumTags\(\$savedId, \$tags\)/,
    'saveAlbum must synchronize the persisted album tags'
);
assert.match(
    actionSource,
    /'tagLinks'\s*=>\s*\$tagLinks/,
    'album JSON must expose Typecho tag archive links'
);

const galleryScript = gallerySource.match(/<script>\s*([\s\S]*?)\s*<\/script>/);
assert.ok(galleryScript, 'album gallery manager script must be present');
const createGalleryManager = new Function(`${galleryScript[1]}\nreturn albumGalleryManager;`)();
const gallery = createGalleryManager('', true);
const normalizedAlbum = gallery.normalizeAlbum({
    tags: ['旅行'],
    tagLinks: [{ name: '旅行', slug: 'travel', url: '/tag/travel' }]
});
assert.deepEqual(
    normalizedAlbum.tagLinks,
    [{ name: '旅行', slug: 'travel', url: '/tag/travel' }],
    'album normalization must preserve clickable Typecho tag links'
);
assert.match(
    gallerySource,
    /class="album-detail-tag-link"[^>]*:href="tag\.url"[^>]*x-text="tag\.name"/,
    'album detail tags must link to their Typecho archive'
);
assert.match(
    stylesheet,
    /\.album-detail-tag-link\s*\{[^}]*text-decoration/s,
    'clickable album tags must have an explicit link style'
);

assert.match(
    functionsSource,
    /include_once 'core\/album-tags\.php'/,
    'the theme must load the shared album-tag archive query helper'
);
assert.match(
    functionsSource,
    /icefoxGetVisibleTagAlbumCount\(\(int\) \$tag\['mid'\]\)/,
    'the tag cloud must include visible albums in each shared tag count'
);
assert.match(
    functionsSource,
    /\$maxCount\s*=\s*max\(1,\s*max\(array_column\(\$tags, 'count'\)\)\)/,
    'album-only tags must not cause division by zero in tag cloud sizing'
);
assert.match(
    archiveSource,
    /icefoxGetTagArchiveAlbums\(\$this\)[\s\S]*?components\/tag-albums\.php/,
    'Typecho tag archives must load matching albums'
);
assert.match(
    archiveSource,
    /elseif \(!\$tagAlbums\)/,
    'a tag archive containing albums must not render the global empty state'
);
assert.ok(
    fs.existsSync('core/album-tags.php') && fs.existsSync('components/tag-albums.php'),
    'album tag archive helpers and markup must be present'
);
const albumTagHelper = fs.readFileSync('core/album-tags.php', 'utf8');
assert.match(
    albumTagHelper,
    /\\\\TypechoPlugin\\\\IcefoxPlugin\\\\Plugin[\s\S]*?\\\\TypechoPlugin\\\\Icefox\\\\Plugin[\s\S]*?getTagArchiveAlbums/,
    'the theme tag helper must prefer IcefoxPlugin while older installations transition'
);
assert.doesNotMatch(
    albumTagHelper,
    /->from\([^)]*icefox_/,
    'the theme tag helper must not read plugin-owned tables directly'
);
assert.ok(
    fs.existsSync('scripts/migrate-album-tags.php'),
    'existing installations must have an explicit album-tag migration command'
);

console.log('Album tag relationships and archive behavior are present');
