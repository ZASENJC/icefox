const assert = require('node:assert/strict');
const fs = require('node:fs');

const functionsSource = fs.readFileSync('functions.php', 'utf8');
const headerSource = fs.readFileSync('header.php', 'utf8');
const pluginClientSource = fs.readFileSync('assets/js/icefox-plugin.js', 'utf8');

assert.match(
    functionsSource,
    /Form_Element_Radio\(\s*'uploadStorage'[\s\S]*?'local'[\s\S]*?'object'[\s\S]*?'local'/,
    'theme settings must offer local and object storage with local as the safe default'
);
assert.match(
    headerSource,
    /'uploadStorage'\s*=>/,
    'the selected upload target must be published through ICEFOX_CONFIG'
);
assert.match(
    pluginClientSource,
    /function appendStorageTarget\(formData\)[\s\S]*?formData\.set\('storage',[\s\S]*?local[\s\S]*?object/,
    'the centralized plugin client must normalize and append the storage target'
);

for (const file of [
    'components/modals/editor.php',
    'edit-page.php',
    'components/modals/album-editor.php'
]) {
    const source = fs.readFileSync(file, 'utf8');
    assert.match(
        source,
        /ICEFOX_PLUGIN\.appendStorageTarget\(formData\)/,
        `${file} must append the configured upload target before submitting media`
    );
}

console.log('Object storage theme contract verified');
