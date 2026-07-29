const assert = require('node:assert/strict');
const fs = require('node:fs');

const stylesheet = fs.readFileSync('assets/css/icefox.css', 'utf8');
const rules = [...stylesheet.matchAll(/([^{}]+)\{([^{}]*)\}/g)].map((match) => ({
    selectors: match[1].split(',').map((selector) => selector.trim()),
    declarations: match[2],
}));

function declarationsFor(selector) {
    return rules
        .filter((rule) => rule.selectors.includes(selector))
        .map((rule) => rule.declarations)
        .join('\n');
}

const topControls = ['tc-home', 'tc-links', 'tc-music', 'tc-album', 'tc-edit', 'tc-setting'];

for (const control of topControls) {
    const baseDeclarations = declarationsFor(`.top-container .${control}`);
    assert.match(
        baseDeclarations,
        /border-radius:\s*50%\s*;/,
        `${control} must keep the friend-link button's circular hover background`
    );
    assert.match(
        baseDeclarations,
        /transition:\s*all 0\.3s ease\s*;/,
        `${control} must animate hover changes like the friend-link button`
    );

    const hoverDeclarations = declarationsFor(`.top-container .${control}:hover`);
    assert.match(
        hoverDeclarations,
        /background-color:\s*rgba\(0, 0, 0, 0\.1\)\s*;/,
        `${control} must show the friend-link button's background on hover`
    );
    assert.match(
        hoverDeclarations,
        /transform:\s*scale\(1\.1\)\s*;/,
        `${control} must scale like the friend-link button on hover`
    );
}

console.log('Top navigation controls share the friend-link hover animation');
