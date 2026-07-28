const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/icefox-plugin.js', 'utf8');
const window = {
    ICEFOX_CONFIG: {
        actionUrl: '/blog/index.php/action/icefox'
    },
    location: {
        href: 'https://example.com/blog/albums'
    }
};

vm.runInNewContext(source, { window, URL });

const plugin = window.ICEFOX_PLUGIN;
assert.ok(plugin, 'the plugin client must be published as window.ICEFOX_PLUGIN');
assert.equal(Object.isFrozen(plugin.actions), true, 'the plugin action list must be immutable');
assert.deepEqual(
    Object.keys(plugin.actions).sort(),
    [
        'addComment',
        'createPost',
        'getAlbum',
        'getAlbums',
        'getFriendLinks',
        'getLikes',
        'like',
        'saveAlbum'
    ],
    'the plugin client must list every action used by the theme'
);

assert.equal(
    plugin.url(plugin.actions.getAlbum, { album: '旅行 相册' }),
    'https://example.com/blog/index.php/action/icefox?do=getAlbum&album=%E6%97%85%E8%A1%8C+%E7%9B%B8%E5%86%8C',
    'plugin URLs must preserve non-rewrite paths and encode parameters'
);
assert.throws(
    () => plugin.url('unknownAction'),
    /Unknown Icefox plugin action/,
    'unknown plugin actions must fail before a network request is made'
);

console.log('Plugin API client contract verified');
