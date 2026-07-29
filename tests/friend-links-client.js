const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/friend-links.js', 'utf8');
const requests = [];

const serverLinks = [
    {
        id: 'friend-a',
        name: '朋友 A',
        url: 'https://a.example.com',
        avatar: '',
        description: '第一个朋友',
        sort: 10
    }
];

const window = {
    ICEFOX_CONFIG: {
        friendLinksUrl: '/blog/links'
    },
    location: {
        href: 'https://example.com/blog/archive'
    },
    document: {
        body: { style: {} }
    },
    confirm: () => true,
    fetch: async (url, options = {}) => {
        requests.push({ url, options });
        const body = options.body ? JSON.parse(options.body) : null;

        if (!options.method || options.method === 'GET') {
            return {
                ok: true,
                json: async () => ({
                    success: true,
                    data: serverLinks,
                    canEdit: true,
                    token: 'fresh-token'
                })
            };
        }

        if (body.action === 'save') {
            return {
                ok: true,
                json: async () => ({
                    success: true,
                    data: [{ ...body.link, id: body.link.id || 'friend-b' }],
                    canEdit: true,
                    token: 'next-token',
                    message: '友情链接已保存'
                })
            };
        }

        if (body.action === 'delete') {
            return {
                ok: true,
                json: async () => ({
                    success: true,
                    data: [],
                    canEdit: true,
                    token: 'delete-token',
                    message: '友情链接已删除'
                })
            };
        }

        throw new Error('Unexpected request');
    }
};

vm.runInNewContext(source, { window, URL, console });

const manager = window.icefoxFriendLinksManager({ mode: 'modal' });
manager.$nextTick = callback => callback();

(async () => {
    await manager.openModal();
    assert.equal(manager.linksModalShow, true, 'opening the modal must expose the friend-link dialog');
    assert.equal(manager.canEdit, true, 'the server decides whether edit controls are available');
    assert.equal(manager.links.length, 1, 'opening the modal must load the independent page data');
    assert.equal(requests[0].url, 'https://example.com/blog/links?icefox_links_api=1');
    assert.equal(requests[0].options.credentials, 'same-origin', 'friend-link reads must stay in the current Typecho session');

    manager.startCreate();
    manager.form.name = '朋友 B';
    manager.form.url = 'https://b.example.com';
    manager.form.description = '新朋友';
    manager.form.sort = 20;
    await manager.saveLink();

    assert.equal(requests[1].options.method, 'POST', 'saving a friend link must use POST');
    assert.match(requests[1].url, /icefox_links_api=1/);
    assert.match(requests[1].url, /_=fresh-token/);
    assert.deepEqual(JSON.parse(requests[1].options.body), {
        action: 'save',
        link: {
            id: '',
            name: '朋友 B',
            url: 'https://b.example.com',
            avatar: '',
            description: '新朋友',
            sort: 20
        }
    });
    assert.equal(manager.links[0].id, 'friend-b', 'the modal must adopt the server-normalized saved record');
    assert.equal(manager.editorOpen, false, 'a successful save must return to the list');

    manager.startEdit(manager.links[0]);
    await manager.deleteLink();
    assert.equal(JSON.parse(requests[2].options.body).action, 'delete');
    assert.equal(manager.links.length, 0, 'a successful delete must refresh the displayed list');

    manager.startCreate();
    manager.form.name = '坏链接';
    manager.form.url = 'javascript:alert(1)';
    await manager.saveLink();
    assert.equal(requests.length, 3, 'invalid URLs must be rejected before a request is sent');
    assert.match(manager.formError, /HTTP|HTTPS/);

    manager.closeModal();
    assert.equal(window.document.body.style.overflow, '', 'closing the modal must restore page scrolling');
    console.log('Friend-link modal client contract verified');
})().catch(error => {
    console.error(error);
    process.exit(1);
});
