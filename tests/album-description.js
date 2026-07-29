const assert = require('node:assert/strict');
const fs = require('node:fs');

const editorSource = fs.readFileSync('components/modals/album-editor.php', 'utf8');
const editorScript = editorSource.match(/<script>\s*([\s\S]*?)\s*<\/script>/);
assert.ok(editorScript, 'album editor manager script must be present');
assert.match(
    editorSource,
    /<span>相册说明<\/span>[\s\S]*?<textarea[^>]*x-model="description"[^>]*maxlength="1000"/,
    'album editor must expose a bounded description field'
);

const createEditorManager = new Function(`${editorScript[1]}\nreturn albumEditorManager;`)();
global.document = { body: { style: { overflow: '' } } };

const editor = createEditorManager();
editor.openModal({ detail: { album: { id: '7', name: '旅行', description: '沿途所见' } } });
assert.equal(editor.description, '沿途所见', 'editing an album must restore its description');

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
        shouldStageObjectFiles: () => false
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

const gallerySource = fs.readFileSync('components/album-gallery.php', 'utf8');
const galleryScript = gallerySource.match(/<script>\s*([\s\S]*?)\s*<\/script>/);
assert.ok(galleryScript, 'album gallery manager script must be present');
assert.match(
    gallerySource,
    /class="album-detail-description"[^>]*x-show="album && album\.description"[^>]*x-text="album && album\.description"/,
    'album detail must render the description as escaped text'
);

const createGalleryManager = new Function(`${galleryScript[1]}\nreturn albumGalleryManager;`)();
const gallery = createGalleryManager('', true);
assert.equal(
    gallery.normalizeAlbum({ description: '沿途所见' }).description,
    '沿途所见',
    'album normalization must preserve the description'
);

const pluginSource = fs.readFileSync('plugins/Icefox/Plugin.php', 'utf8');
assert.match(pluginSource, /`description` text/, 'album schema must define a description column');
assert.match(
    pluginSource,
    /in_array\('description',[\s\S]*?ALTER TABLE[^\n]*ADD COLUMN `description` text/,
    'existing album tables must migrate the description column'
);

const actionSource = fs.readFileSync('plugins/Icefox/Action.php', 'utf8');
assert.match(actionSource, /request->get\('description'/, 'saveAlbum must read the description');
assert.match(actionSource, /mb_strlen[\s\S]*?> 1000/, 'saveAlbum must enforce the description length limit');
assert.match(
    actionSource,
    /\$requestedDescription === null[\s\S]*?\$existing\['description'\]/,
    'older clients that omit the description must preserve the stored value'
);
assert.match(actionSource, /'description'\s*=>\s*\$description/, 'saveAlbum must persist the description');
assert.match(
    actionSource,
    /'description'\s*=>\s*\(string\)\s*\(\$album\['description'\]/,
    'album JSON must expose the description'
);

async function run() {
    editor.description = '  沿途所见  ';
    editor.$dispatch = () => {};
    await editor.submitAlbum();
    assert.ok(submittedForm, 'album edit must submit a form');
    assert.deepEqual(
        submittedForm.entries.find(([key]) => key === 'description'),
        ['description', '沿途所见'],
        'album edits must submit the trimmed description'
    );
    console.log('Album description behavior is present');
}

run().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
