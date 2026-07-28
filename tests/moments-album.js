const assert = require('assert');
const fs = require('fs');

const source = fs.readFileSync('components/album-gallery.php', 'utf8');
const scriptMatch = source.match(/<script>\s*([\s\S]*?)\s*<\/script>/);
assert.ok(scriptMatch, 'album gallery manager script must be present');

const createManager = new Function(`${scriptMatch[1]}\nreturn albumGalleryManager;`)();

function responseWith(albums) {
    return {
        ok: true,
        json: async () => ({ success: true, data: { albums } })
    };
}

async function loadAlbums(showMomentsAlbum, albums) {
    global.fetch = async () => responseWith(albums);
    const manager = createManager('', showMomentsAlbum);
    await manager.load();
    return manager.albums;
}

async function run() {
    const defaultAlbums = await loadAlbums(true, []);
    assert.strictEqual(defaultAlbums.length, 1, 'enabled moments album must be shown when the plugin list is empty');
    assert.strictEqual(defaultAlbums[0].name, '朋友圈', 'the default album must use the moments name');
    assert.strictEqual(defaultAlbums[0].slug, 'moments', 'the default album must use the stable moments slug');

    const pluginAlbums = await loadAlbums(true, [
        { id: 'trip', slug: 'trip', name: '旅行' },
        { id: 'moments-id', slug: 'moments', name: '朋友圈', photos: ['photo.jpg'] },
        { id: 'legacy-moments', slug: 'legacy', name: '朋友圈' }
    ]);
    assert.strictEqual(pluginAlbums.length, 2, 'duplicate plugin-provided moments albums must be collapsed');
    assert.strictEqual(pluginAlbums[0].slug, 'moments', 'the moments album must be the first album');
    assert.strictEqual(pluginAlbums[0].photos.length, 1, 'the plugin-provided moments photos must be preserved');

    const hiddenAlbums = await loadAlbums(false, [
        { id: 'moments-id', slug: 'moments', name: '朋友圈' },
        { id: 'trip', slug: 'trip', name: '旅行' }
    ]);
    assert.deepStrictEqual(hiddenAlbums.map(album => album.slug), ['trip'], 'disabled moments album must be hidden');
}

global.window = {
    ICEFOX_CONFIG: {
        actionUrl: '/action/icefox',
        albumUrl: '/albums'
    },
    location: {
        href: 'https://example.com/albums'
    }
};
global.document = {
    querySelector: () => null
};
global.CustomEvent = class CustomEvent {};

run()
    .then(() => console.log('Moments album visibility contract verified'))
    .catch(error => {
        console.error(error);
        process.exit(1);
    });
