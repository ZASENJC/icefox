const assert = require('node:assert/strict');
const fs = require('node:fs');

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

class FileReaderMock {
    readAsDataURL(file) {
        this.onload({ target: { result: `data:${file.type};base64,preview` } });
    }
}

class FormDataMock {
    constructor() {
        this.values = new Map();
        FormDataMock.instances.push(this);
    }

    append(key, value) {
        this.values.set(key, value);
    }

    set(key, value) {
        this.values.set(key, value);
    }
}

FormDataMock.instances = [];

function loadManager(source, managerName) {
    const scriptMatch = source.match(/<script>\s*([\s\S]*?)\s*<\/script>/);
    assert.ok(scriptMatch, `${managerName} script must be present`);
    return new Function(`${scriptMatch[1]}\nreturn ${managerName};`)();
}

function imageFile(name) {
    return { name, type: 'image/png', size: 128 };
}

function clipboardItem(file) {
    return {
        kind: 'file',
        type: file.type,
        getAsFile: () => file
    };
}

function pasteEvent({ items = [], files = [] } = {}) {
    return {
        clipboardData: { items, files },
        defaultPrevented: false,
        preventDefault() {
            this.defaultPrevented = true;
        }
    };
}

const alerts = [];
global.FileReader = FileReaderMock;
global.FormData = FormDataMock;
global.alert = message => alerts.push(message);
global.setTimeout = () => {};
global.fetch = async () => ({
    ok: true,
    json: async () => ({ success: true, message: '发布成功', redirect: '/' })
});
global.window = {
    ICEFOX_CONFIG: { homeUrl: '/' },
    ICEFOX_PLUGIN: {
        actions: { createPost: 'createPost' },
        appendStorageTarget: formData => formData.set('storage', 'local'),
        postUrl: async () => '/action/icefox?do=createPost&_=fresh-token'
    },
    location: {
        href: 'https://example.com/current',
        origin: 'https://example.com',
        replace: () => {}
    },
    setTimeout: () => {}
};

async function verifyPublisher(publisher) {
    const source = fs.readFileSync(publisher.file, 'utf8');
    assert.match(
        source,
        /<textarea[\s\S]*?@paste="handleMediaPaste\(\$event\)"[\s\S]*?<\/textarea>/,
        `${publisher.file} must listen for image paste on its content textarea`
    );

    const createManager = loadManager(source, publisher.managerName);

    const textManager = createManager();
    const textPaste = pasteEvent({
        items: [{ kind: 'string', type: 'text/plain', getAsFile: () => null }]
    });
    textManager.handleMediaPaste(textPaste);
    assert.equal(textPaste.defaultPrevented, false, `${publisher.file} must preserve normal text paste`);
    assert.equal(textManager.mediaFiles.length, 0, `${publisher.file} must ignore clipboard text`);

    const itemManager = createManager();
    const itemImage = imageFile('clipboard-item.png');
    const duplicateListImage = imageFile('clipboard-list.png');
    const itemPaste = pasteEvent({
        items: [clipboardItem(itemImage)],
        files: [duplicateListImage]
    });
    itemManager.handleMediaPaste(itemPaste);
    assert.equal(itemPaste.defaultPrevented, true, `${publisher.file} must consume image paste`);
    assert.equal(itemManager.mediaFiles.length, 1, `${publisher.file} must not duplicate clipboard images`);
    assert.equal(itemManager.mediaFiles[0].file, itemImage, `${publisher.file} must prefer clipboard items`);
    assert.match(itemManager.mediaFiles[0].preview, /^data:image\/png/, `${publisher.file} must preview pasted images`);

    const fallbackManager = createManager();
    const fallbackImage = imageFile('clipboard-fallback.png');
    const fallbackPaste = pasteEvent({ files: [fallbackImage] });
    fallbackManager.handleMediaPaste(fallbackPaste);
    assert.equal(fallbackManager.mediaFiles[0].file, fallbackImage, `${publisher.file} must fall back to clipboard files`);

    alerts.length = 0;
    const limitManager = createManager();
    limitManager.mediaFiles = Array.from({ length: 8 }, (_, index) => ({
        file: imageFile(`existing-${index}.png`),
        type: 'image/png',
        preview: 'data:image/png;base64,existing'
    }));
    limitManager.handleMediaPaste(pasteEvent({
        files: [imageFile('ninth.png'), imageFile('tenth.png')]
    }));
    assert.equal(limitManager.mediaFiles.length, 9, `${publisher.file} must retain the nine-image limit`);
    assert.ok(alerts.some(message => message.includes('最多只能上传9张图片')), `${publisher.file} must explain the image limit`);

    alerts.length = 0;
    const videoManager = createManager();
    videoManager.mediaFiles = [{
        file: { name: 'existing.mp4', type: 'video/mp4' },
        type: 'video/mp4',
        preview: 'data:video/mp4;base64,existing'
    }];
    const blockedPaste = pasteEvent({ files: [imageFile('blocked.png')] });
    videoManager.handleMediaPaste(blockedPaste);
    assert.equal(blockedPaste.defaultPrevented, true, `${publisher.file} must consume an image paste rejected by media rules`);
    assert.equal(videoManager.mediaFiles.length, 1, `${publisher.file} must not mix pasted images with video`);
    assert.ok(alerts.some(message => message.includes('不能再添加其他文件')), `${publisher.file} must explain the video conflict`);

    const submitManager = createManager();
    const submittedImage = imageFile('submitted.png');
    submitManager.handleMediaPaste(pasteEvent({ files: [submittedImage] }));
    await submitManager.submitPost();
    const submittedForm = FormDataMock.instances.pop();
    assert.equal(submittedForm.values.get('media_0'), submittedImage, `${publisher.file} must submit pasted images as post media`);
}

publishers.reduce(
    (previous, publisher) => previous.then(() => verifyPublisher(publisher)),
    Promise.resolve()
).then(() => console.log('Publishing clipboard image paste verified'))
    .catch(error => {
        console.error(error);
        process.exit(1);
    });
