const portalDropdown = document.querySelector('.portal-dropdown');

if (portalDropdown) {
    document.addEventListener('click', (event) => {
        if (!portalDropdown.contains(event.target)) {
            portalDropdown.open = false;
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && portalDropdown.open) {
            portalDropdown.open = false;
            portalDropdown.querySelector('summary').focus();
        }
    });
}
