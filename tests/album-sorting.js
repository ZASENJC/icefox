const assert = require('node:assert/strict');
const fs = require('node:fs');

const gallerySource = fs.readFileSync('components/album-gallery.php', 'utf8');
const galleryScript = gallerySource.match(/<script>\s*([\s\S]*?)\s*<\/script>/);
assert.ok(galleryScript, 'album gallery manager script must be present');

const createGalleryManager = new Function(`${galleryScript[1]}\nreturn albumGalleryManager;`)();
const gallery = createGalleryManager('', true);

assert.equal(gallery.normalizeAlbum({ sortOrder: '12' }).sortOrder, 12);
assert.equal(gallery.normalizeAlbum({ sort_order: 7 }).sortOrder, 7);
assert.equal(gallery.normalizeAlbum({ order: 'invalid' }).sortOrder, 0);
assert.equal(gallery.normalizeAlbum({ sortOrder: -3 }).sortOrder, 0);

const sortedAlbums = gallery.normalizeAlbumList([
    { id: 'regular-late', slug: 'regular-late', name: '普通靠后', sort_order: 20 },
    { id: 'pinned-late', slug: 'pinned-late', name: '置顶靠后', isPinned: true, sortOrder: 30 },
    { id: 'moments', slug: 'moments', name: '朋友圈', isPinned: true, sortOrder: 999 },
    { id: 'regular-default', slug: 'regular-default', name: '普通默认' },
    { id: 'pinned-early', slug: 'pinned-early', name: '置顶靠前', is_pinned: 1, sort_order: 3 },
    { id: 'regular-early-a', slug: 'regular-early-a', name: '普通靠前 A', sortOrder: 5 },
    { id: 'regular-early-b', slug: 'regular-early-b', name: '普通靠前 B', sortOrder: 5 }
]);

assert.deepEqual(
    sortedAlbums.map(album => album.slug),
    [
        'moments',
        'pinned-early',
        'pinned-late',
        'regular-default',
        'regular-early-a',
        'regular-early-b',
        'regular-late'
    ],
    'moments must stay first, then regular albums sort by pin state and ascending sort order'
);

gallery.albums = sortedAlbums;
assert.equal(
    gallery.nextSortOrder(),
    31,
    'new albums must continue after the largest regular album sort order'
);

const momentsOnlyGallery = createGalleryManager('', true);
momentsOnlyGallery.albums = [momentsOnlyGallery.normalizeAlbum({
    id: 'moments',
    slug: 'moments',
    name: '朋友圈',
    sortOrder: 999
})];
assert.equal(momentsOnlyGallery.nextSortOrder(), 1, 'the first regular album must start at sort order one');

let editorOpenDetail = null;
global.CustomEvent = class CustomEvent {
    constructor(type, options) {
        this.type = type;
        this.detail = options.detail;
    }
};
global.window = {
    dispatchEvent(event) {
        editorOpenDetail = event.detail;
    }
};
gallery.openEditor();
assert.equal(editorOpenDetail.suggestedSortOrder, 31, 'the create action must pass the next sort order to the editor');
gallery.openEditor(sortedAlbums[1]);
assert.equal(editorOpenDetail.suggestedSortOrder, undefined, 'editing an existing album must preserve its stored sort order');

const hiddenMomentsGallery = createGalleryManager('', false);
assert.deepEqual(
    hiddenMomentsGallery.normalizeAlbumList([
        { id: 'moments', slug: 'moments', name: '朋友圈', sortOrder: 999 },
        { id: 'late', slug: 'late', name: '靠后', sortOrder: 20 },
        { id: 'early', slug: 'early', name: '靠前', sortOrder: 2 }
    ]).map(album => album.slug),
    ['early', 'late'],
    'hidden moments albums must not participate in regular album sorting'
);

const editorSource = fs.readFileSync('components/modals/album-editor.php', 'utf8');
const editorScript = editorSource.match(/<script>\s*([\s\S]*?)\s*<\/script>/);
assert.ok(editorScript, 'album editor manager script must be present');

const createEditorManager = new Function(`${editorScript[1]}\nreturn albumEditorManager;`)();
global.document = { body: { style: { overflow: '' } } };

const editor = createEditorManager();
editor.openModal({ detail: { album: { id: '7', name: '旅行', sort_order: '12' } } });
assert.equal(editor.sortOrder, 12, 'editing an album must restore its sort order');
assert.equal(editor.isMomentsAlbum, false, 'regular albums must expose the sort control');

editor.openModal({ detail: { album: { id: 'moments', name: '朋友圈', sortOrder: 99 } } });
assert.equal(editor.isMomentsAlbum, true, 'the moments album must be recognized by stable identity');
assert.equal(editor.sortOrder, 0, 'the moments album must not retain a manual sort order');

editor.openModal({ detail: { album: null, suggestedSortOrder: 31 } });
assert.equal(editor.sortOrder, 31, 'new albums must use the suggested next sort order');
editor.openModal({ detail: { album: null } });
assert.equal(editor.sortOrder, 1, 'new albums without list context must start at sort order one');
assert.match(editorSource, /type="number"[^>]*x-model\.number="sortOrder"/);
assert.match(editorSource, /x-show="!isMomentsAlbum"[\s\S]*?>排序序号</);

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
    ICEFOX_CONFIG: { uploadStorage: 'local' },
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

async function submittedSortEntries(album, uploadOnly = false) {
    submittedForm = null;
    const manager = createEditorManager();
    manager.openModal({ detail: { album, uploadOnly } });
    manager.mediaFiles = uploadOnly ? [{ file: { name: 'photo.jpg' } }] : [];
    manager.$dispatch = () => {};
    await manager.submitAlbum();
    return submittedForm.entries.filter(([key]) => key === 'sortOrder');
}

async function run() {
    assert.deepEqual(
        await submittedSortEntries({ id: '7', name: '旅行', sortOrder: 18 }),
        [['sortOrder', '18']],
        'regular album edits must submit their sort order'
    );
    assert.deepEqual(
        await submittedSortEntries({ id: 'moments', name: '朋友圈', sortOrder: 18 }),
        [],
        'moments album edits must not submit a manual sort order'
    );
    assert.deepEqual(
        await submittedSortEntries({ id: '7', name: '旅行', sortOrder: 18 }, true),
        [],
        'photo-only uploads must not overwrite album sorting'
    );
    console.log('Album sorting behavior is present');
}

run().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
