'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const handlers = {};
const inside = {};
const panel = { hidden: true };
const profile = {
    contains: target => target === inside,
    querySelector: () => panel,
    addEventListener: (name, callback) => { handlers[name] = callback; },
};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../assets/js/admin-profile.js'), 'utf8'), {
    document: {
        querySelectorAll: () => [profile],
        activeElement: null,
        addEventListener: (name, callback) => { handlers[name] = callback; },
    },
});
handlers.pointerenter({ pointerType: 'touch' });
assert.equal(panel.hidden, true);
handlers.pointerenter({ pointerType: 'mouse' });
assert.equal(panel.hidden, false);
handlers.click({ target: inside });
assert.equal(panel.hidden, false);
handlers.pointerleave();
assert.equal(panel.hidden, true);
handlers.focusin();
assert.equal(panel.hidden, false);
handlers.focusout({ relatedTarget: inside });
assert.equal(panel.hidden, false);
handlers.focusout({ relatedTarget: {} });
assert.equal(panel.hidden, true);
handlers.focusin();
handlers.keydown({ key: 'Escape' });
assert.equal(panel.hidden, true);
handlers.focusin();
handlers.click({ target: {} });
assert.equal(panel.hidden, true);
const layout = fs.readFileSync(require('node:path').join(__dirname, '../includes/layout.php'), 'utf8');
for (const [role, path] of [['Admin','admin/personal-information.php'], ['Student','student/information.php'], ['Faculty','faculty/information.php']]) {
    assert.ok(layout.includes(`'${role}' => '${path}'`));
}
assert.ok(layout.includes('app_url($profilePaths[$role])'));
console.log('PASS: hover, touch, focus, Escape, outside dismissal, and personal information link');
