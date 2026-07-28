const assert = require('node:assert/strict');
const fs = require('node:fs');

const modalSource = fs.readFileSync('components/modals/album-editor.php', 'utf8');
const scriptMatch = modalSource.match(/<script>\s*([\s\S]*?)<\/script>/);
assert.ok(scriptMatch, 'album editor script must be present');

const createManager = new Function(`${scriptMatch[1]}\nreturn albumEditorManager;`)();

const parserManager = createManager();
parserManager.remotePhotoUrls = [
    '',
    '  https://img.example.com/one.jpg  ',
    'http://cdn.example.com/two.webp',
    ''
].join('\n');

assert.deepEqual(parserManager.parseRemotePhotoUrls(), [
    'https://img.example.com/one.jpg',
    'http://cdn.example.com/two.webp'
]);
assert.equal(parserManager.isRemotePhotoUrlValid('https://img.example.com/photo.jpg'), true);
assert.equal(parserManager.isRemotePhotoUrlValid('javascript:alert(1)'), false);
assert.equal(parserManager.isRemotePhotoUrlValid('ftp://img.example.com/photo.jpg'), false);

let submittedForm = null;
global.FormData = class FormDataMock {
    constructor() {
        this.entries = [];
    }

    append(key, value) {
        this.entries.push([key, value]);
    }
};
global.window = {
    ICEFOX_CONFIG: { actionUrl: '/action/icefox' },
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

const submitManager = createManager();
submitManager.albumId = '7';
submitManager.albumName = '旅行';
submitManager.uploadOnly = true;
submitManager.remotePhotoUrls = 'https://img.example.com/remote.jpg';
submitManager.$dispatch = () => {};

submitManager.submitAlbum().then(() => {
    assert.ok(submittedForm, 'remote-only upload must submit the form');
    const remotePhotos = submittedForm.entries.find(([key]) => key === 'remotePhotos');
    assert.deepEqual(remotePhotos, [
        'remotePhotos',
        JSON.stringify(['https://img.example.com/remote.jpg'])
    ]);
    console.log('Album remote photo URL behavior is present');
}).catch(error => {
    console.error(error);
    process.exitCode = 1;
});
