'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const script = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/admin-navigation.js'), 'utf8');
function setup(storage, blocked = false) {
    const groups = [
        { dataset: { navGroup: 'users' }, open: false },
        { dataset: { navGroup: 'courses', current: 'true' }, open: false },
        { dataset: { navGroup: 'departments' }, open: false },
    ];
    groups.forEach(group => { group.addEventListener = (event, callback) => { group.toggle = callback; }; });
    const navigation = { dataset: { storageKey: '/admin-navigation:1' }, querySelectorAll: () => groups };
    vm.runInNewContext(script, {
        document: { querySelectorAll: () => [navigation] },
        localStorage: {
            getItem: () => { if (blocked) throw Error('blocked'); return storage; },
            setItem: (key, value) => { if (blocked) throw Error('blocked'); storage = value; },
        },
    });
    return { groups, saved: () => JSON.parse(storage) };
}
let test = setup('{"users":false,"courses":false,"departments":true}');
assert.equal(test.groups[0].open, false);
assert.equal(test.groups[1].open, false);
assert.equal(test.groups[2].open, true);
test.groups[0].open = true;
test.groups[0].toggle();
assert.equal(test.saved().users, true);
assert.equal(test.groups[2].open, true);
for (const value of ['broken JSON', 'null', '[]', '{}']) {
    test = setup(value);
    assert.equal(test.groups[0].open, false);
    assert.equal(test.groups[1].open, false);
    assert.equal(test.groups[2].open, false);
}
test = setup('{}', true);
test.groups[0].open = false;
assert.doesNotThrow(() => test.groups[0].toggle());
console.log('PASS: collapsed defaults, no forced current-page expansion, saved user choices, independent groups, malformed/blocked storage');
