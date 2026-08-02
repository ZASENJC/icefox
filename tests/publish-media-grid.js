const assert = require('node:assert/strict');
const fs = require('node:fs');

const css = fs.readFileSync('assets/css/icefox.css', 'utf8');

function ruleFor(selector) {
    const escapedSelector = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const match = css.match(new RegExp(`${escapedSelector}\\s*\\{([^}]*)\\}`));
    assert.ok(match, `${selector} rule must exist`);
    return match[1];
}

const gridRule = ruleFor('.edit-media-preview');
assert.match(
    gridRule,
    /grid-template-columns:\s*repeat\(3,\s*minmax\(0,\s*1fr\)\)/,
    'publishing media must fill the available width with three equal columns'
);

assert.doesNotMatch(
    css,
    /\.edit-media-preview\.media-count-(?:1|2|4)\s*\{/,
    'image counts must not replace the consistent three-column grid'
);

for (const selector of ['.media-preview-item', '.media-add-btn']) {
    const itemRule = ruleFor(selector);
    assert.match(itemRule, /width:\s*100%/, `${selector} must fill its grid column`);
    assert.match(itemRule, /height:\s*auto/, `${selector} height must follow its width`);
    assert.match(itemRule, /aspect-ratio:\s*1/, `${selector} must remain square`);
}

for (const file of ['components/modals/editor.php', 'edit-page.php']) {
    const source = fs.readFileSync(file, 'utf8');
    assert.match(
        source,
        /class="edit-media-preview"[\s\S]*?<template[\s\S]*?<\/template>[\s\S]*?class="media-add-btn"/,
        `${file} must keep the add button in the same grid after the image items`
    );
}

console.log('Publishing media uses a full-width three-column grid');
