const assert = require('node:assert/strict');
const fs = require('node:fs');

const headSource = fs.readFileSync('components/head.php', 'utf8');
const stylesheet = fs.readFileSync('assets/css/icefox.css', 'utf8');

assert.match(
    headSource,
    /<div class="top-container-left">\s*<a class="tc-home" data-icon="home" href="<\?php \$this->options->siteUrl\(\); \?>" aria-label="返回首页">/,
    'the home link must be the leftmost top navigation button and use the configured site URL'
);

const homePosition = headSource.indexOf('class="tc-home"');
const albumPosition = headSource.indexOf('class="tc-album"');
const linksPosition = headSource.indexOf('class="tc-links"');
assert.ok(
    homePosition >= 0 && homePosition < albumPosition && albumPosition < linksPosition,
    'the left navigation controls must be ordered home, album, then friend links'
);

assert.match(
    headSource,
    /class="tc-home"[\s\S]*?<\?php \$this->need\("components\/svgs\/home-outline\.php"\); \?>/,
    'the home control must use the shared outline icon style'
);
assert.match(
    stylesheet,
    /\.top-container \.tc-home,\s*\.top-container \.tc-links,\s*\.top-container \.tc-music,/,
    'the home link must share the existing 40px top navigation button layout'
);

for (const icon of ['home-outline.php']) {
    const iconPath = `components/svgs/${icon}`;
    assert.ok(fs.existsSync(iconPath), `${iconPath} must exist`);
    const iconSource = fs.readFileSync(iconPath, 'utf8');
    assert.match(iconSource, /viewBox="0 0 24 24"/, `${icon} must use the shared 24px viewBox`);
    assert.match(iconSource, /aria-hidden="true"/, `${icon} must be hidden from assistive technology`);
}

console.log('Top home button structure and icon states verified');
