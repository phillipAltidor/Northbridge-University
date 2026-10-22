'use strict';

document.querySelectorAll('.admin-navigation').forEach((navigation) => {
    const key = navigation.dataset.storageKey;
    let saved = {};
    try {
        const value = JSON.parse(localStorage.getItem(key) || '{}');
        if (value && typeof value === 'object' && !Array.isArray(value)) saved = value;
    } catch { /* Navigation works without browser storage. */ }

    navigation.querySelectorAll('[data-nav-group]').forEach((group) => {
        const id = group.dataset.navGroup;
        if (typeof saved[id] === 'boolean') group.open = saved[id];
        group.addEventListener('toggle', () => {
            saved[id] = group.open;
            try { localStorage.setItem(key, JSON.stringify(saved)); }
            catch { /* Keep the native disclosure usable when storage is blocked. */ }
        });
    });
});
