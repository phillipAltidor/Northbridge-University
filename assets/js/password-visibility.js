'use strict';

document.querySelectorAll('[data-password-visibility]').forEach((toggle) => {
    const fields = (toggle.getAttribute('aria-controls') || '')
        .split(/\s+/)
        .map((id) => document.getElementById(id))
        .filter((field) => field && field.form === toggle.form);
    const updateVisibility = () => {
        fields.forEach((field) => { field.type = toggle.checked ? 'text' : 'password'; });
    };
    toggle.checked = false;
    updateVisibility();
    toggle.addEventListener('change', updateVisibility);
    window.addEventListener('pageshow', () => {
        toggle.checked = false;
        updateVisibility();
    });
});
