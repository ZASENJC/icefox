(function (global) {
    'use strict';

    const actions = Object.freeze({
        getLikes: 'getLikes',
        like: 'like',
        addComment: 'addComment',
        getFriendLinks: 'getFriendLinks',
        createPost: 'createPost',
        getAlbums: 'getAlbums',
        getAlbum: 'getAlbum',
        getSecurityToken: 'getSecurityToken',
        stageAlbumUpload: 'stageAlbumUpload',
        saveAlbum: 'saveAlbum'
    });
    const knownActions = Object.keys(actions).map(key => actions[key]);

    function url(action, params) {
        if (knownActions.indexOf(action) === -1) {
            throw new Error(`Unknown Icefox plugin action: ${action}`);
        }

        const endpoint = new URL(global.ICEFOX_CONFIG.actionUrl, global.location.href);
        endpoint.searchParams.set('do', action);

        Object.keys(params || {}).forEach(key => {
            const value = params[key];
            if (value !== undefined && value !== null) {
                endpoint.searchParams.set(key, String(value));
            }
        });

        return endpoint.toString();
    }

    function appendStorageTarget(formData) {
        const configuredTarget = global.ICEFOX_CONFIG && global.ICEFOX_CONFIG.uploadStorage;
        formData.set('storage', ['local', 'object'].includes(configuredTarget) ? configuredTarget : 'local');
        return formData;
    }

    function shouldStageObjectFiles(files) {
        const selectedFiles = Array.from(files || []);
        if (selectedFiles.length === 0) {
            return false;
        }

        const config = global.ICEFOX_CONFIG || {};
        const uploadMaxBytes = Number(config.phpUploadMaxBytes);
        const postMaxBytes = Number(config.phpPostMaxBytes);
        if (!Number.isFinite(uploadMaxBytes) || uploadMaxBytes <= 0
            || !Number.isFinite(postMaxBytes) || postMaxBytes <= 0) {
            return true;
        }

        let estimatedRequestBytes = 512 * 1024;
        for (const file of selectedFiles) {
            const fileSize = Number(file && file.size);
            if (!Number.isFinite(fileSize) || fileSize < 0 || fileSize > uploadMaxBytes) {
                return true;
            }
            estimatedRequestBytes += fileSize + (64 * 1024);
        }

        return estimatedRequestBytes > postMaxBytes;
    }

    async function postUrl(action, params) {
        const response = await global.fetch(url(actions.getSecurityToken), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store'
        });
        const result = await response.json();
        if (!response.ok || !result.success || !result.token) {
            throw new Error(result.message || '无法刷新登录状态，请重新登录');
        }

        const endpoint = new URL(url(action, params), global.location.href);
        endpoint.searchParams.set('_', result.token);
        return endpoint.toString();
    }

    global.ICEFOX_PLUGIN = Object.freeze({ actions, url, postUrl, appendStorageTarget, shouldStageObjectFiles });
})(window);
