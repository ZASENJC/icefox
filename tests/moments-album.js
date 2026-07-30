const assert = require('assert');
const fs = require('fs');

const source = fs.readFileSync('components/album-gallery.php', 'utf8');
const headSource = fs.readFileSync('components/head.php', 'utf8');
const styles = fs.readFileSync('assets/css/icefox.css', 'utf8');
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
    const manager = createManager('', true);
    const coverSource = Array.from({ length: 12 }, (_, index) => ({
        src: `photo-${index + 1}.jpg`,
        alt: `照片 ${index + 1}`
    }));
    const zeroRandomCover = manager.buildMomentsCoverPhotos(coverSource, () => 0);
    const highRandomCover = manager.buildMomentsCoverPhotos(coverSource, () => 0.999999);
    assert.strictEqual(zeroRandomCover.length, 9, 'moments cover must render as a nine-cell grid');
    assert.strictEqual(new Set(zeroRandomCover.map(photo => photo.src)).size, 9, 'albums with enough photos must sample nine different images');
    assert.ok(zeroRandomCover.every(photo => coverSource.includes(photo)), 'moments cover cells must come from that album');
    assert.notDeepStrictEqual(
        zeroRandomCover.map(photo => photo.src),
        highRandomCover.map(photo => photo.src),
        'different random sequences must produce different cover arrangements'
    );

    const shortCoverSource = coverSource.slice(0, 2);
    const repeatedCover = manager.buildMomentsCoverPhotos(shortCoverSource, () => 0);
    assert.strictEqual(repeatedCover.length, 9, 'moments albums with fewer photos must still fill all nine cells');
    assert.deepStrictEqual(
        new Set(repeatedCover.map(photo => photo.src)),
        new Set(shortCoverSource.map(photo => photo.src)),
        'short moments albums must only repeat their own photos'
    );
    assert.deepStrictEqual(manager.buildMomentsCoverPhotos([], () => 0), [], 'empty moments albums must keep the placeholder');

    const normalizedMoments = manager.normalizeAlbum({
        id: 'moments',
        name: '朋友圈',
        photos: coverSource
    });
    const normalizedRegular = manager.normalizeAlbum({
        id: 'trip',
        name: '旅行',
        photos: coverSource
    });
    assert.strictEqual(normalizedMoments.coverPhotos.length, 9, 'only the moments album must prepare a nine-cell cover');
    assert.deepStrictEqual(normalizedRegular.coverPhotos, [], 'regular album cover behavior must remain unchanged');

    const headerClasses = new Set();
    const albumHeader = {
        style: { backgroundImage: '' },
        classList: {
            toggle(className, enabled) {
                if (enabled) headerClasses.add(className);
                else headerClasses.delete(className);
            }
        }
    };
    const momentsHeaderTitle = { hidden: true };
    global.document.querySelector = selector => {
        if (selector === '[data-album-header]') return albumHeader;
        if (selector === '[data-moments-header-title]') return momentsHeaderTitle;
        return null;
    };

    manager.updateHeader(normalizedMoments, () => 0);
    assert.ok(headerClasses.has('is-moments-album'), 'moments detail must enable its dedicated header treatment');
    assert.strictEqual(momentsHeaderTitle.hidden, false, 'moments detail must reveal the centered title');
    assert.ok(
        coverSource.some(photo => albumHeader.style.backgroundImage.includes(photo.src)),
        'moments detail header must use a random photo from the moments album'
    );

    manager.updateHeader(normalizedRegular, () => 0);
    assert.ok(!headerClasses.has('is-moments-album'), 'regular album detail must not use the moments header treatment');
    assert.strictEqual(momentsHeaderTitle.hidden, true, 'regular album detail must keep the moments title hidden');
    assert.ok(albumHeader.style.backgroundImage.includes(normalizedRegular.cover), 'regular album detail must keep its normal cover');

    assert.match(source, /class="album-card-cover-grid"[^>]*x-show="album\.isMoments && album\.coverPhotos\.length"/);
    assert.match(source, /x-for="\(photo, index\) in album\.coverPhotos"/);
    assert.match(headSource, /data-moments-header-title[^>]*hidden[^>]*>朋友圈</);
    assert.match(styles, /\.album-card-cover-grid\s*\{[\s\S]*?grid-template-columns:\s*repeat\(3,\s*minmax\(0,\s*1fr\)\)/);
    assert.match(styles, /\.album-card-cover-grid\s*\{[\s\S]*?gap:\s*0;/, 'moments cover grid cells must be seamless');
    assert.match(styles, /\.album-card-cover-grid img\s*\{[\s\S]*?filter:\s*blur\(/);
    assert.match(styles, /\.album-header\.is-moments-album::before\s*\{[\s\S]*?filter:\s*blur\(/, 'moments header photo must be blurred');
    assert.match(styles, /\.album-header\.is-moments-album::after\s*\{[\s\S]*?background:\s*rgb\(0 0 0 \/ [^)]+\)/, 'moments header photo must be darkened');
    assert.match(styles, /\.album-header-title\s*\{[\s\S]*?justify-content:\s*center;[\s\S]*?font-weight:\s*700;/, 'moments header title must be centered and bold');

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
    ICEFOX_PLUGIN: {
        actions: {
            getAlbum: 'getAlbum',
            getAlbums: 'getAlbums'
        },
        url: action => `/action/icefox?do=${action}`
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
