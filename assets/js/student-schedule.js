'use strict';

document.querySelector('.student-schedule-filter select')?.addEventListener('change', (event) => {
    event.target.form.requestSubmit();
});
