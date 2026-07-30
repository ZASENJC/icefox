const fs = require('fs');
const path = require('path');
const assert = require('assert');

const root = path.resolve(__dirname, '..');
const css = fs.readFileSync(path.join(root, 'assets/css/icefox.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/icefox.js'), 'utf8');
const header = fs.readFileSync(path.join(root, 'header.php'), 'utf8');

assert.match(css, /line-height:\s*1\.35/);
assert.match(css, /\.post-list \.pcc-comment-expand[\s\S]*\.post-detail-article \.pcc-comment-expand/);
assert.match(css, /\.pcc-comment-expand/);
assert.match(css, /\.pcc-comment-content\s*\{[\s\S]*display:\s*inline/);
assert.match(css, /\.pcc-comment-text\s*\{[\s\S]*word-break:\s*break-all/);
assert.match(js, /function initCommentLengthLimits/);
assert.match(js, /function toggleCommentExpansion/);
assert.match(js, /text\.dataset\.fullText/);
assert.match(js, /text\.dataset\.collapsedText/);
assert.match(js, /expand\.textContent = '收起'/);
assert.match(js, /lineHeight \* 4/);
assert.match(js, /Array\.from\(fullText\)/);
assert.match(header, /initCommentLengthLimits\(commentItem\)/);

console.log('comment length contract passed');
