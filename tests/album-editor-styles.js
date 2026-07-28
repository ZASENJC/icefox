const assert = require('node:assert/strict');
const fs = require('node:fs');

const css = fs.readFileSync('assets/css/icefox.css', 'utf8');

assert.match(
    css,
    /\.album-editor-modal\s*\{[^}]*--album-editor-field-background:\s*#262626;[^}]*--album-editor-field-color:\s*#f5f5f5;/s,
    'album editor should define a shared dark field palette'
);

assert.match(
    css,
    /\.album-editor-field input\[type="text"\],[\s\S]*?\.album-editor-field textarea\s*\{[^}]*background:\s*var\(--album-editor-field-background\);[^}]*color:\s*var\(--album-editor-field-color\);/,
    'album text fields and remote photo URLs should share the dark field palette'
);

assert.match(
    css,
    /\.album-editor-field input\[type="file"\]\s*\{[^}]*background:\s*var\(--album-editor-field-background\);[^}]*color:\s*var\(--album-editor-field-color\);/s,
    'album photo picker should use the same dark field palette'
);

console.log('album editor styles verified');
