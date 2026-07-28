const assert = require('node:assert/strict');
const fs = require('node:fs');

const gallerySource = fs.readFileSync('components/album-gallery.php', 'utf8');
const stylesheet = fs.readFileSync('assets/css/icefox.css', 'utf8');

assert.match(
    stylesheet,
    /\.album-page-title\s*\{[^}]*font-size:\s*24px;/s,
    'the album page title must use the larger 24px heading size'
);

assert.match(
    gallerySource,
    /<div class="album-card-heading"[^>]*>[\s\S]*?class="album-card-name"[\s\S]*?class="album-card-address"[\s\S]*?<\/div>\s*<div class="album-card-meta"[^>]*>[\s\S]*?class="album-card-photo-count"[\s\S]*?<\/div>/,
    'album cards must render the name and address on one line above the photo count'
);
assert.match(
    gallerySource,
    /class="album-card-address"[^>]*x-show="album\.address"[^>]*x-text="album\.address"/,
    'album cards must show the normalized address when it is present'
);
assert.match(
    stylesheet,
    /\.album-card-heading\s*\{[^}]*display:\s*flex;[^}]*justify-content:\s*space-between;[^}]*color:\s*var\(--text-color\);[^}]*font-size:\s*14px;[^}]*font-weight:\s*500;/s,
    'the album heading must align the name and address while defining their shared typography'
);
assert.match(
    stylesheet,
    /\.album-card-address\s*\{[^}]*margin-left:\s*auto;[^}]*text-align:\s*right;/s,
    'the album address must align to the right edge of the metadata row'
);

console.log('Album card location layout is present');
