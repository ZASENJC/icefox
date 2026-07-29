const assert = require('node:assert/strict');
const fs = require('node:fs');

function loadManager(file, functionName) {
    const source = fs.readFileSync(file, 'utf8');
    const script = source.match(/<script>\s*([\s\S]*?)\s*<\/script>/);
    assert.ok(script, `${file} must contain its manager script`);
    return {
        create: new Function(`${script[1]}\nreturn ${functionName};`)(),
        source
    };
}

global.document = {
    body: { style: { overflow: '' } },
    querySelector: () => null
};
global.CustomEvent = class CustomEvent {
    constructor(type, options) {
        this.type = type;
        this.detail = options.detail;
    }
};

const editorModule = loadManager('components/modals/album-editor.php', 'albumEditorManager');
const editor = editorModule.create();
const existingPhotos = Array.from({ length: 99 }, (_, index) => ({ src: `photo-${index}.jpg` }));
editor.openModal({ detail: { album: { id: '7', name: '旅行', photos: existingPhotos } } });

assert.equal(editor.existingPhotoCount, 99, 'the editor must account for photos already stored in the album');
assert.equal(editor.remainingPhotoSlots, 1, 'a regular album with 99 photos must accept one more photo');
global.FileReader = class FileReaderMock {
    readAsDataURL() {}
};
global.alert = () => {};
editor.handleMediaSelect({
    target: {
        files: [{ name: 'last-photo.jpg', type: 'image/jpeg' }],
        value: 'selected'
    }
});
assert.equal(editor.mediaFiles.length, 1, 'selected photos must reserve their slots before preview loading finishes');
assert.equal(editor.remainingPhotoSlots, 0, 'the 100th pending photo must fill the album');
editor.handleMediaSelect({
    target: {
        files: [{ name: 'over-limit.jpg', type: 'image/jpeg' }],
        value: 'selected'
    }
});
assert.equal(editor.mediaFiles.length, 1, 'the 101st photo must be rejected even while previews are loading');

assert.match(
    editorModule.source,
    /x-show="!isMomentsAlbum"[\s\S]*?<input[^>]*type="file"/,
    'the moments editor must hide local photo uploads'
);
assert.match(
    editorModule.source,
    /x-show="!isMomentsAlbum"[\s\S]*?x-model="remotePhotoUrls"/,
    'the moments editor must hide remote photo uploads'
);

const galleryModule = loadManager('components/album-gallery.php', 'albumGalleryManager');
let editorOpenCount = 0;
global.window = {
    dispatchEvent() {
        editorOpenCount++;
    }
};

const momentsGallery = galleryModule.create('moments', true);
momentsGallery.album = momentsGallery.normalizeAlbum({
    id: 'moments',
    slug: 'moments',
    name: '朋友圈',
    isMoments: true,
    photos: []
});
momentsGallery.openPrimaryAction();
assert.equal(editorOpenCount, 0, 'the moments detail action must not open the manual uploader');

const regularGallery = galleryModule.create('trip', true);
regularGallery.album = regularGallery.normalizeAlbum({ id: 'trip', name: '旅行', photos: [] });
regularGallery.openPrimaryAction();
assert.equal(editorOpenCount, 1, 'regular album details must retain manual uploads');

let submitted = false;
let alertMessage = '';
global.FormData = class FormDataMock {};
global.alert = message => {
    alertMessage = message;
};
global.fetch = async () => {
    submitted = true;
    throw new Error('the moments upload must be rejected before fetching');
};
global.window = {
    ICEFOX_CONFIG: { uploadStorage: 'local' },
    ICEFOX_PLUGIN: {
        actions: { saveAlbum: 'saveAlbum' },
        postUrl: async () => '/action/icefox?do=saveAlbum',
        shouldStageObjectFiles: () => false,
        appendStorageTarget() {}
    },
    setTimeout() {}
};

const momentsEditor = editorModule.create();
momentsEditor.openModal({
    detail: {
        album: { id: 'moments', slug: 'moments', name: '朋友圈', isMoments: true, photos: [] },
        uploadOnly: true
    }
});
momentsEditor.mediaFiles = [{ file: { name: 'manual.jpg', size: 1 } }];
momentsEditor.$dispatch = () => {};

async function run() {
    await momentsEditor.submitAlbum();
    assert.equal(submitted, false, 'manual moments uploads must not reach the API');
    assert.match(alertMessage, /朋友圈相册.*动态同步/, 'the rejection must direct photos through moments sync');
    console.log('Album photo limit and moments upload UI contracts passed');
}

run().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
