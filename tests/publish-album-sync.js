const assert = require('assert');
const fs = require('fs');

const publishers = [
    {
        file: 'components/modals/editor.php',
        managerName: 'editorModalManager'
    },
    {
        file: 'edit-page.php',
        managerName: 'editPageManager'
    }
];

class FormDataMock {
    constructor() {
        this.values = new Map();
        FormDataMock.instances.push(this);
    }

    append(key, value) {
        this.values.set(key, value);
    }
}

FormDataMock.instances = [];

function loadManager(source, managerName) {
    const scriptMatch = source.match(/<script>\s*([\s\S]*?)\s*<\/script>/);
    assert.ok(scriptMatch, `${managerName} script must be present`);
    return new Function(`${scriptMatch[1]}\nreturn ${managerName};`)();
}

async function assertSyncContract(publisher) {
    const source = fs.readFileSync(publisher.file, 'utf8');
    assert.match(source, /同步到「朋友圈」相册/, `${publisher.file} must show the album sync option`);
    assert.match(source, /type="checkbox"[^>]*x-model="syncToAlbum"/, `${publisher.file} must expose an accessible checkbox`);
    const albumIconPattern = publisher.file === 'components/modals/editor.php'
        ? /class="option-icon editor-option-icon editor-option-album-icon"/
        : /class="option-icon album-sync-image-icon"[^>]*>[\s\S]*?<svg[^>]*viewBox="0 0 24 24"/;
    assert.match(source, albumIconPattern, `${publisher.file} must use the image icon UI for album sync`);
    assert.doesNotMatch(source, />▧</, `${publisher.file} must not use a text glyph as the album icon`);

    const createManager = loadManager(source, publisher.managerName);
    for (const [syncToAlbum, expectedValue] of [[false, '0'], [true, '1']]) {
        const manager = createManager();
        assert.strictEqual(manager.syncToAlbum, false, `${publisher.file} must default album sync to off`);

        manager.postContent = '测试动态';
        manager.syncToAlbum = syncToAlbum;
        await manager.submitPost();

        const submittedForm = FormDataMock.instances.pop();
        assert.ok(submittedForm, `${publisher.file} must submit multipart form data`);
        assert.strictEqual(
            submittedForm.values.get('syncToAlbum'),
            expectedValue,
            `${publisher.file} must send the album sync choice as ${expectedValue}`
        );
        assert.strictEqual(
            manager.submitStatus,
            '发布成功，已同步 1 张图片到朋友圈相册',
            `${publisher.file} must show the plugin's actual publishing result`
        );
    }
}

global.FormData = FormDataMock;
global.alert = () => {};
global.setTimeout = () => {};
global.fetch = async () => ({
    ok: true,
    json: async () => ({
        success: true,
        message: '发布成功，已同步 1 张图片到朋友圈相册',
        redirect: '/'
    })
});
global.window = {
    ICEFOX_CONFIG: {
        actionUrl: '/action/icefox',
        homeUrl: '/'
    },
    ICEFOX_PLUGIN: {
        actions: { createPost: 'createPost' },
        url: action => `/action/icefox?do=${action}`,
        postUrl: async action => `/action/icefox?do=${action}&_=fresh-token`
    },
    location: {
        href: 'https://example.com/current',
        origin: 'https://example.com',
        replace: () => {}
    },
    setTimeout: () => {}
};

publishers.reduce(
    (previous, publisher) => previous.then(() => assertSyncContract(publisher)),
    Promise.resolve()
).then(() => console.log('Publishing album sync contract verified'))
    .catch(error => {
        console.error(error);
        process.exit(1);
    });
