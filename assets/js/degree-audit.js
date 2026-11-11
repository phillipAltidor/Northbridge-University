(() => {
    'use strict';
    const worksheet = document.querySelector('.degree-worksheet');
    if (!worksheet) return;
    let opener = null;
    worksheet.addEventListener('click', event => {
        const course = event.target.closest('[data-course-dialog]');
        if (course) {
            event.preventDefault();
            const dialog = document.getElementById(course.dataset.courseDialog);
            if (dialog && dialog.tagName === 'DIALOG') { opener = course; dialog.showModal(); }
        }
        const close = event.target.closest('[data-dialog-close]');
        if (close) close.closest('dialog').close();
        const toggle = event.target.closest('[data-audit-toggle]');
        if (toggle) {
            const blocks = [...worksheet.querySelectorAll('details.audit-block')];
            const open = !blocks.some(block => block.open);
            blocks.forEach(block => { block.open = open; });
            toggle.textContent = open ? 'Collapse all' : 'Expand all';
        }
    });
    worksheet.querySelectorAll('dialog').forEach(dialog => {
        dialog.addEventListener('close', () => { if (opener) opener.focus(); });
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const box = dialog.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
        });
    });
})();
