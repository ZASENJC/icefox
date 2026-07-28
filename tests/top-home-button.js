const assert = require('node:assert/strict');
const fs = require('node:fs');

const headSource = fs.readFileSync('components/head.php', 'utf8');
const scriptSource = fs.readFileSync('assets/js/icefox.js', 'utf8');
const stylesheet = fs.readFileSync('assets/css/icefox.css', 'utf8');

assert.match(
    headSource,
    /<div class="top-container-left">\s*<a class="tc-home" data-icon="home" href="<\?php \$this->options->siteUrl\(\); \?>" aria-label="返回首页">/,
    'the home link must be the leftmost top navigation button and use the configured site URL'
);
assert.match(
    headSource,
    /data-icon="home"><\?php \$this->need\("components\/svgs\/home\.php"\); \?><\/div>/,
    'the filled home icon must be preloaded for the hero state'
);
assert.match(
    headSource,
    /data-icon="home-outline"><\?php \$this->need\("components\/svgs\/home-outline\.php"\); \?><\/div>/,
    'the outline home icon must be preloaded for the scrolled state'
);
assert.match(
    scriptSource,
    /'\.tc-home, \.tc-user, \.tc-music, \.tc-album, \.tc-edit, \.tc-setting'/,
    'the home icon must participate in the existing scroll-state icon swap'
);
assert.match(
    stylesheet,
    /\.top-container \.tc-home,\s*\.top-container \.tc-music,/,
    'the home link must share the existing 40px top navigation button layout'
);

for (const icon of ['home.php', 'home-outline.php']) {
    const iconPath = `components/svgs/${icon}`;
    assert.ok(fs.existsSync(iconPath), `${iconPath} must exist`);
    const iconSource = fs.readFileSync(iconPath, 'utf8');
    assert.match(iconSource, /viewBox="0 0 24 24"/, `${icon} must use the shared 24px viewBox`);
    assert.match(iconSource, /aria-hidden="true"/, `${icon} must be hidden from assistive technology`);
}

console.log('Top home button structure and icon states verified');
