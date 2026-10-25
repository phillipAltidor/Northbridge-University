'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/student-schedule.js'), 'utf8');
let change;
let submitted = 0;
vm.runInNewContext(source, {
    document: { querySelector: () => ({ addEventListener: (name, callback) => {
        assert.equal(name, 'change'); change = callback;
    } }) },
});
change({ target: { form: { requestSubmit: () => { submitted++; } } } });
assert.equal(submitted, 1);
vm.runInNewContext(source, { document: { querySelector: () => null } });
console.log('PASS: semester selection submits immediately; missing selector is harmless');
