'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const script = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/password-visibility.js'), 'utf8');

for (const ids of [['password'], ['password', 'password_confirmation']]) {
    const form = {};
    const fields = Object.fromEntries(ids.map((id) => [id, { form, type: 'password', value: 'Unchanged-Test-Password!' }]));
    fields.other = { form: {}, type: 'password' };
    const handlers = {};
    const toggle = {
        form, checked: true,
        getAttribute: () => [...ids, 'other', 'missing'].join(' '),
        addEventListener: (name, callback) => { handlers[name] = callback; },
    };
    vm.runInNewContext(script, {
        document: { querySelectorAll: () => [toggle], getElementById: (id) => fields[id] },
        window: { addEventListener: (name, callback) => { handlers[name] = callback; } },
    });
    assert.equal(toggle.checked, false);
    ids.forEach((id) => assert.equal(fields[id].type, 'password'));
    toggle.checked = true;
    handlers.change();
    ids.forEach((id) => assert.equal(fields[id].type, 'text'));
    assert.equal(fields.other.type, 'password');
    toggle.checked = false;
    handlers.change();
    ids.forEach((id) => assert.equal(fields[id].type, 'password'));
    toggle.checked = true;
    handlers.change();
    handlers.pageshow();
    assert.equal(toggle.checked, false);
    ids.forEach((id) => {
        assert.equal(fields[id].type, 'password');
        assert.equal(fields[id].value, 'Unchanged-Test-Password!');
    });
}
console.log('PASS: login/reset visibility, hide/show, history restore, form isolation, and unchanged values');
