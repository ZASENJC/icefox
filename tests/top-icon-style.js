const assert = require('node:assert/strict');
const fs = require('node:fs');

const headSource = fs.readFileSync('components/head.php', 'utf8');
const scriptSource = fs.readFileSync('assets/js/icefox.js', 'utf8');
const stylesheet = fs.readFileSync('assets/css/icefox.css', 'utf8');
const leftStart = headSource.indexOf('<div class="top-container-left">');
const leftEnd = headSource.indexOf('<div class="top-container-right">');
const leftControls = headSource.slice(leftStart, leftEnd);

for (const icon of ['home', 'album', 'links']) {
    assert.match(
        leftControls,
        new RegExp(`components\\/svgs\\/${icon}-outline\\.php`),
        `${icon} must use the shared Heroicons 24 Outline series`
    );
}

for (const icon of ['home', 'album', 'links', 'edit', 'setting', 'music', 'plus']) {
    const iconPath = `components/svgs/${icon}-outline.php`;
    assert.ok(fs.existsSync(iconPath), `${iconPath} must exist`);
    const iconSource = fs.readFileSync(iconPath, 'utf8');
    assert.match(iconSource, /viewBox="0 0 24 24"/, `${icon} must use the shared 24px viewBox`);
    assert.match(iconSource, /fill="none"/, `${icon} must use the outline series`);
    assert.match(iconSource, /stroke-width="1\.5"/, `${icon} must use the same stroke weight`);
    assert.match(iconSource, /stroke="currentColor"/, `${icon} must inherit the adaptive top icon color`);
    assert.match(iconSource, /aria-hidden="true"/, `${icon} must be decorative beside its labelled control`);
}

assert.doesNotMatch(leftControls, /<svg\b/, 'left controls must use icon-library components, not one-off inline SVGs');
assert.doesNotMatch(headSource, /class="preloaded-icons"/, 'outline-only top icons must not preload alternate solid styles');
assert.doesNotMatch(scriptSource, /toggleIcons/, 'scrolling must change color without changing icon style');
assert.match(
    stylesheet,
    /\.top-container > \.top-container-left,\s*\.top-container > \.top-container-right\s*\{[^}]*gap:\s*5px;/s,
    'both top control groups must use the same 5px spacing'
);
assert.match(
    stylesheet,
    /\.top-container svg\s*\{[^}]*height:\s*22px;[^}]*width:\s*22px;/s,
    'every top icon must use the same 22px rendered box'
);
assert.doesNotMatch(stylesheet, /\.tc-links svg\s*\{/, 'friend links must not override the shared icon size');

const licenseSource = fs.readFileSync('components/svgs/HEROICONS_LICENSE', 'utf8');
assert.match(licenseSource, /MIT License/, 'vendored Heroicons must retain their license');
assert.match(licenseSource, /Tailwind Labs, Inc\./, 'vendored Heroicons must retain upstream attribution');

console.log('Top navigation icons share the Heroicons 24 Outline style and dimensions');
