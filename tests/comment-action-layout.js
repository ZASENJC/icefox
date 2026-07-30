const fs = require('fs');
const path = require('path');
const assert = require('assert');

const root = path.resolve(__dirname, '..');
const css = fs.readFileSync(path.join(root, 'assets/css/icefox.css'), 'utf8');

const modalRule = css.match(/\.post-list \.post-time-comment-modal,\s*\.post-detail-article \.post-time-comment-modal\s*\{([^}]*)\}/);
const actionRule = css.match(/\.post-list \.ptcm-good,[\s\S]*?\.post-detail-article \.ptcm-comment\s*\{([^}]*)\}/);

assert.ok(modalRule, 'feed and detail action modal rule must exist');
assert.match(modalRule[1], /left:\s*-154px/);
assert.match(modalRule[1], /width:\s*144px/);
assert.match(modalRule[1], /height:\s*32px/);
assert.match(modalRule[1], /padding:\s*4px\s*;/);
assert.match(modalRule[1], /box-sizing:\s*border-box/);
assert.match(modalRule[1], /grid-template-columns:\s*repeat\(2,\s*minmax\(0,\s*1fr\)\)/);

assert.ok(actionRule, 'feed and detail action item rule must exist');
assert.match(actionRule[1], /width:\s*100%/);
assert.match(actionRule[1], /padding:\s*0/);
assert.match(actionRule[1], /box-sizing:\s*border-box/);

console.log('comment action layout contract passed');
