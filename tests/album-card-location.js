const assert = require('node:assert/strict');
const fs = require('node:fs');

const gallerySource = fs.readFileSync('components/album-gallery.php', 'utf8');
const stylesheet = fs.readFileSync('assets/css/icefox.css', 'utf8');

assert.match(
    gallerySource,
    /<div class="album-card-meta"[^>]*>[\s\S]*?class="album-card-photo-count"[\s\S]*?class="album-card-address"[\s\S]*?<\/div>/,
    'album cards must render the photo count and address in the same metadata row'
);
assert.match(
    gallerySource,
    /class="album-card-address"[^>]*x-show="album\.address"[^>]*x-text="album\.address"/,
    'album cards must show the normalized address when it is present'
);
assert.match(
    stylesheet,
    /\.album-card-meta\s*\{[^}]*display:\s*flex;[^}]*justify-content:\s*space-between;/s,
    'the album metadata row must keep the photo count and address on opposite sides'
);
assert.match(
    stylesheet,
    /\.album-card-address\s*\{[^}]*margin-left:\s*auto;[^}]*text-align:\s*right;/s,
    'the album address must align to the right edge of the metadata row'
);

console.log('Album card location layout is present');
