const assert = require('node:assert/strict');
const fs = require('node:fs');

const modulePath = 'assets/js/top-icon-contrast.js';
assert.ok(fs.existsSync(modulePath), 'the top icon contrast module must exist');

const contrast = require(`../${modulePath}`);
const stylesheet = fs.readFileSync('assets/css/icefox.css', 'utf8');
const headerSource = fs.readFileSync('header.php', 'utf8');
const moduleSource = fs.readFileSync(modulePath, 'utf8');

assert.equal(contrast.relativeLuminance(255, 255, 255), 1, 'white must have maximum luminance');
assert.equal(contrast.relativeLuminance(0, 0, 0), 0, 'black must have minimum luminance');
assert.equal(contrast.prefersDarkIcons(245, 245, 245), true, 'light backgrounds need dark icons');
assert.equal(contrast.prefersDarkIcons(24, 24, 24), false, 'dark backgrounds need light icons');

const mixedPixels = new Uint8ClampedArray([
    255, 255, 255, 255,
    0, 0, 0, 255,
]);
assert.equal(
    contrast.averageLuminance(mixedPixels),
    0.5,
    'sampling must use the visible pixels across the top navigation area'
);

assert.deepEqual(
    contrast.coverGeometry(1600, 900, 800, 320),
    { width: 800, height: 450, left: 0, top: -65 },
    'landscape backgrounds must match the header cover crop'
);
assert.deepEqual(
    contrast.coverGeometry(600, 900, 800, 320),
    { width: 800, height: 1200, left: 0, top: -440 },
    'portrait backgrounds must match the header cover crop'
);

const classState = new Map();
const videoListeners = new Map();
const drawnMedia = [];
let pendingFrame = null;
let samplingBlocked = false;
const video = {
    videoWidth: 1600,
    videoHeight: 900,
    addEventListener(eventName, listener) {
        videoListeners.set(eventName, listener);
    },
    removeEventListener(eventName, listener) {
        if (videoListeners.get(eventName) === listener) videoListeners.delete(eventName);
    }
};
const topContainer = {
    classList: {
        toggle(className, enabled) {
            classState.set(className, enabled);
        }
    },
    getBoundingClientRect() {
        return { top: 0, height: 56 };
    }
};
const header = {
    querySelector(selector) {
        return selector === 'video' ? video : null;
    },
    getBoundingClientRect() {
        return { top: 0, width: 800, height: 320 };
    }
};
const context = {
    clearRect() {},
    drawImage(media) {
        drawnMedia.push(media);
    },
    getImageData() {
        if (samplingBlocked) throw new Error('cross-origin media');
        return { data: new Uint8ClampedArray([255, 255, 255, 255]) };
    }
};
const documentObject = {
    querySelector(selector) {
        if (selector === '.top-container') return topContainer;
        if (selector === '.header-container') return header;
        return null;
    },
    createElement(tagName) {
        assert.equal(tagName, 'canvas');
        return { getContext: () => context, width: 0, height: 0 };
    }
};
function MutationObserver() {}
MutationObserver.prototype.observe = function () {};
MutationObserver.prototype.disconnect = function () {};
const windowObject = {
    MutationObserver,
    addEventListener() {},
    removeEventListener() {},
    requestAnimationFrame(callback) {
        pendingFrame = callback;
        return 1;
    },
    cancelAnimationFrame() {},
    getComputedStyle() {
        return { backgroundImage: 'none', backgroundColor: 'rgba(0, 0, 0, 0)' };
    }
};

const controller = contrast.init(documentObject, windowObject);
assert.equal(drawnMedia[0], video, 'the current top video frame must be sampled');
assert.equal(classState.get('has-light-background'), true, 'a light video frame must use dark icons');
assert.ok(videoListeners.has('timeupdate'), 'video playback must schedule fresh contrast samples');

videoListeners.get('timeupdate')();
pendingFrame();
assert.equal(drawnMedia.length, 2, 'a progressing video must be sampled again');

samplingBlocked = true;
videoListeners.get('seeked')();
pendingFrame();
assert.equal(
    classState.get('has-media-sampling-fallback'),
    true,
    'blocked video sampling must enable the readable icon fallback'
);
controller.destroy();
assert.equal(videoListeners.size, 0, 'destroying the analyzer must release video listeners');

assert.match(
    moduleSource,
    /new MutationObserver\(/,
    'dynamic album cover changes must trigger a fresh contrast analysis'
);
assert.match(
    moduleSource,
    /getImageData\(/,
    'the rendered background pixels must drive the icon contrast'
);
assert.match(
    moduleSource,
    /header\.querySelector\('video'\)[\s\S]*?videoWidth[\s\S]*?videoHeight[\s\S]*?context\.drawImage\(\s*media,/,
    'top videos must use their current frame in the shared contrast sampler'
);
assert.match(
    moduleSource,
    /\['loadeddata', 'timeupdate', 'seeked'\][\s\S]*?activeVideo\.addEventListener\(eventName, scheduleContrastUpdate\)/,
    'playing and seeking a top video must refresh the icon contrast'
);
assert.match(
    moduleSource,
    /classList\.toggle\(\s*'has-light-background'/,
    'the analysis result must update the top navigation color state'
);
assert.match(
    stylesheet,
    /\.top-container:not\(\.scrolled\)\.has-light-background svg\s*\{[^}]*color:\s*#555555;/s,
    'light backgrounds must switch unscrolled top icons to a dark color'
);
assert.match(
    stylesheet,
    /\.top-container:not\(\.scrolled\)\.has-media-sampling-fallback svg\s*\{[^}]*drop-shadow/s,
    'unreadable video frames must retain a visible icon fallback when canvas sampling is blocked'
);
assert.match(
    headerSource,
    /top-icon-contrast\.js[^\n]*\n[^\n]*icefox\.js/,
    'the contrast module must load before the existing top navigation behavior'
);

console.log('Top icon background contrast behavior verified');
