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

    global.ICEFOX_PLUGIN = Object.freeze({ actions, url });
})(window);
