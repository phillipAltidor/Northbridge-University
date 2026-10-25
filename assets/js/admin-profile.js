'use strict';

document.querySelectorAll('.admin-profile').forEach((profile) => {
    const panel = profile.querySelector('.admin-profile-panel');
    profile.addEventListener('pointerenter', (event) => {
        if (event.pointerType === 'mouse') panel.hidden = false;
    });
    profile.addEventListener('pointerleave', () => {
        if (!profile.contains(document.activeElement)) panel.hidden = true;
    });
    profile.addEventListener('focusin', () => { panel.hidden = false; });
    document.addEventListener('click', (event) => {
        if (!profile.contains(event.target)) panel.hidden = true;
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') panel.hidden = true;
    });
    profile.addEventListener('focusout', (event) => {
        if (!profile.contains(event.relatedTarget)) panel.hidden = true;
    });
});
