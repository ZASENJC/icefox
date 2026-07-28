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

    const createManager = loadManager(source, publisher.managerName);
    const manager = createManager();
    assert.strictEqual(manager.syncToAlbum, false, `${publisher.file} must default album sync to off`);

    manager.postContent = '测试动态';
    manager.syncToAlbum = true;
    await manager.submitPost();

    const submittedForm = FormDataMock.instances.pop();
    assert.ok(submittedForm, `${publisher.file} must submit multipart form data`);
    assert.strictEqual(
        submittedForm.values.get('syncToAlbum'),
        '1',
        `${publisher.file} must send the enabled album sync choice`
    );
}

global.FormData = FormDataMock;
global.alert = () => {};
global.setTimeout = () => {};
global.fetch = async () => ({
    ok: true,
    json: async () => ({ success: true, redirect: '/' })
});
global.window = {
    ICEFOX_CONFIG: {
        actionUrl: '/action/icefox',
        homeUrl: '/'
    },
    location: {
        href: 'https://example.com/current',
        origin: 'https://example.com',
        replace: () => {}
    },
    setTimeout: () => {}
};

Promise.all(publishers.map(assertSyncContract))
    .then(() => console.log('Publishing album sync contract verified'))
    .catch(error => {
        console.error(error);
        process.exit(1);
    });
