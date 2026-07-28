const assert = require('assert');
const fs = require('fs');

const modalSource = fs.readFileSync('components/modals/editor.php', 'utf8');
const cssSource = fs.readFileSync('assets/css/icefox.css', 'utf8');

for (const icon of ['location', 'visibility', 'album']) {
    assert.match(
        modalSource,
        new RegExp(`class="option-icon editor-option-icon editor-option-${icon}-icon"`),
        `${icon} option must use the shared icon UI`
    );
    assert.ok(
        fs.existsSync(`assets/icons/heroicons-${icon}.svg`),
        `${icon} option must use a real Heroicons asset`
    );
}

assert.doesNotMatch(modalSource, /[⌖◉▧]/, 'editor options must not use text glyphs as icons');
assert.match(
    cssSource,
    /\.editor-modal \.edit-option-item\s*\{[^}]*min-height:\s*56px;[^}]*align-items:\s*center;/s,
    'all editor option rows must share the same height and vertical alignment'
);
assert.match(
    cssSource,
    /\.editor-modal \.editor-option-icon\s*\{[^}]*width:\s*22px;[^}]*height:\s*22px;[^}]*flex:\s*0 0 22px;/s,
    'all editor option icons must use the same 22px box'
);
assert.match(
    cssSource,
    /\.editor-modal \.option-label\s*\{[^}]*font-size:\s*15px;[^}]*line-height:\s*22px;/s,
    'all editor option labels must share the same type size and line height'
);

console.log('Editor option sizing and alignment verified');
