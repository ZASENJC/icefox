const assert = require('node:assert/strict');
const fs = require('node:fs');

const gallerySource = fs.readFileSync('components/album-gallery.php', 'utf8');
const stylesheet = fs.readFileSync('assets/css/icefox.css', 'utf8');
const galleryScript = gallerySource.match(/<script>\s*([\s\S]*?)\s*<\/script>/);
assert.ok(galleryScript, 'album gallery manager script must be present');

const createGalleryManager = new Function(`${galleryScript[1]}\nreturn albumGalleryManager;`)();
const gallery = createGalleryManager('', true);

assert.equal(gallery.normalizeAlbum({ isPinned: true }).isPinned, true);
assert.equal(gallery.normalizeAlbum({ pinned: '1' }).isPinned, true);
assert.equal(gallery.normalizeAlbum({ is_pinned: 1 }).isPinned, true);
assert.equal(gallery.normalizeAlbum({ isPinned: '0' }).isPinned, false);

const sortedAlbums = gallery.normalizeAlbumList([
    { id: 'moments', slug: 'moments', name: '朋友圈' },
    { id: 'regular-new', slug: 'regular-new', name: '新相册' },
    { id: 'pinned-new', slug: 'pinned-new', name: '置顶新相册', is_pinned: 1 },
    { id: 'pinned-old', slug: 'pinned-old', name: '置顶旧相册', pinned: true },
    { id: 'regular-old', slug: 'regular-old', name: '旧相册' }
]);
assert.deepEqual(
    sortedAlbums.map(album => album.slug),
    ['moments', 'pinned-new', 'pinned-old', 'regular-new', 'regular-old'],
    'moments must stay first, followed by pinned albums without changing order inside each group'
);

assert.match(gallerySource, /class="album-card-pinned"[^>]*x-show="album\.isPinned"/);
assert.match(gallerySource, /class="album-card-pinned"[\s\S]*?<svg[^>]*aria-hidden="true"/);
assert.match(gallerySource, /class="album-card-pinned"[\s\S]*?<svg[^>]*fill="currentColor"/);
const pinnedBadgeStyle = stylesheet.match(/\.album-card-pinned\s*\{([^}]*)\}/);
assert.ok(pinnedBadgeStyle, 'the pinned SVG badge style must be present');
assert.match(
    pinnedBadgeStyle[1],
    /top:\s*10px;[\s\S]*left:\s*10px;[\s\S]*color:\s*#e5484d;/,
    'the simple red SVG must stay anchored to the cover top-left'
);
assert.doesNotMatch(
    pinnedBadgeStyle[1],
    /background:|border-radius:|box-shadow:/,
    'the simple pinned SVG must not use a badge background, circle, or shadow'
);
assert.doesNotMatch(
    stylesheet,
    /\.album-card-pinned\s*\{[^}]*background:\s*#e5484d;/s,
    'the old red circular badge must be removed'
);

const editorSource = fs.readFileSync('components/modals/album-editor.php', 'utf8');
const editorScript = editorSource.match(/<script>\s*([\s\S]*?)\s*<\/script>/);
assert.ok(editorScript, 'album editor manager script must be present');

const createEditorManager = new Function(`${editorScript[1]}\nreturn albumEditorManager;`)();
global.document = { body: { style: { overflow: '' } } };

const editor = createEditorManager();
editor.openModal({ detail: { album: { id: '7', name: '旅行', is_pinned: 1 } } });
assert.equal(editor.isPinned, true, 'editing a pinned album must restore its switch');
editor.openModal({ detail: { album: null } });
assert.equal(editor.isPinned, false, 'creating an album must reset the pinned switch');
assert.match(editorSource, /type="checkbox"[^>]*x-model="isPinned"/);
assert.match(editorSource, />置顶相册</);

global.FormData = class FormDataMock {
    constructor() {
        this.entries = [];
    }

    append(key, value) {
        this.entries.push([key, value]);
    }
};

let submittedForm = null;
global.window = {
    ICEFOX_CONFIG: { actionUrl: '/action/icefox', uploadStorage: 'local' },
    ICEFOX_PLUGIN: {
        actions: { saveAlbum: 'saveAlbum' },
        appendStorageTarget() {},
        postUrl: async action => `/action/icefox?do=${action}`,
        shouldStageObjectFiles: () => false,
        url: action => `/action/icefox?do=${action}`
    },
    setTimeout() {}
};
global.fetch = async (url, options) => {
    submittedForm = options.body;
    return {
        ok: true,
        async json() {
            return { success: true };
        }
    };
};
global.alert = message => {
    throw new Error(`unexpected alert: ${message}`);
};

async function submitWithPinned(isPinned, uploadOnly = false) {
    submittedForm = null;
    const manager = createEditorManager();
    manager.albumId = '7';
    manager.albumName = '旅行';
    manager.isPinned = isPinned;
    manager.uploadOnly = uploadOnly;
    manager.mediaFiles = uploadOnly ? [{ file: { name: 'photo.jpg' } }] : [];
    manager.$dispatch = () => {};
    await manager.submitAlbum();
    return submittedForm.entries.filter(([key]) => key === 'isPinned');
}

async function run() {
    assert.deepEqual(await submitWithPinned(true), [['isPinned', '1']]);
    assert.deepEqual(await submitWithPinned(false), [['isPinned', '0']]);
    assert.deepEqual(
        await submitWithPinned(true, true),
        [],
        'photo-only uploads must not overwrite album pinning'
    );
    console.log('Album pinning behavior is present');
}

run().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
