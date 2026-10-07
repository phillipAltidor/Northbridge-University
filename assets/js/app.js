// Confirmation message for important actions
const confirmButtons = document.querySelectorAll('[data-confirm]');

confirmButtons.forEach(function (button) {

    button.addEventListener('click', function (event) {

        const message = button.getAttribute('data-confirm');

        const userConfirmed = confirm(message);

        if (!userConfirmed) {
            event.preventDefault();
        }

    });

});