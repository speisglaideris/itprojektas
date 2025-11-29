console.log("Index scripts loaded successfully.");

// Wait until the DOM is fully loaded
document.addEventListener("DOMContentLoaded", () => {
    // pop up elements
    const pop_up_login = document.getElementById('pop-up-login');
    const pop_up_signup = document.getElementById('pop-up-signup');
    const pop_up_notif = document.getElementById('pop-up-notif');
    const pop_up_notif_photo = document.getElementById('pop-up-notif-photo');

    // pop up buttons
    const pop_up_login_button = document.getElementById('pop-up-login-button');
    const pop_up_login_button_post = document.getElementById('pop-up-login-button-post');
    const pop_up_signup_button = document.getElementById('pop-up-signup-button');
    const pop_up_closer_login = document.getElementById('pop-up-closer-login');
    const pop_up_closer_signup = document.getElementById('pop-up-closer-signup');
    const pop_up_switcher_signup = document.getElementById('replace-with-signup');
    const pop_up_switcher_login = document.getElementById('replace-with-login');
    const pop_up_closer_notif = document.getElementById('pop-up-closer-notif');
    const notif_to_login = document.getElementById('notif-to-login');
    const notif_to_signup = document.getElementById('notif-to-reg');
    const pop_up_closer_photo = document.getElementById('pop-up-closer-notif-photo');


    const safeAddListener = (element, event, handler) => {
        if (element) {
            element.addEventListener(event, handler);
        }
    };

    // pop up commands
    safeAddListener(pop_up_login_button, 'click', () => {
        pop_up_login?.classList.add('show-pop-up');
    });

    safeAddListener(pop_up_login_button_post, 'click', () => {
        pop_up_login?.classList.add('show-pop-up');
    });

    safeAddListener(pop_up_signup_button, 'click', () => {
        pop_up_signup?.classList.add('show-pop-up');
    });

    safeAddListener(pop_up_closer_login, 'click', () => {
        pop_up_login?.classList.remove('show-pop-up');
    });

    safeAddListener(pop_up_closer_signup, 'click', () => {
        pop_up_signup?.classList.remove('show-pop-up');
    });

    safeAddListener(pop_up_switcher_signup, 'click', () => {
        pop_up_login?.classList.remove('show-pop-up');
        pop_up_signup?.classList.add('show-pop-up');
    });

    safeAddListener(pop_up_switcher_login, 'click', () => {
        pop_up_signup?.classList.remove('show-pop-up');
        pop_up_login?.classList.add('show-pop-up');
    });

    safeAddListener(pop_up_closer_notif, 'click', () => {
        pop_up_notif?.classList.remove('show-pop-up');
    });

    safeAddListener(notif_to_login, 'click', () => {
        pop_up_notif?.classList.remove('show-pop-up');
        pop_up_login?.classList.add('show-pop-up');
    });

    safeAddListener(notif_to_signup, 'click', () => {
        pop_up_notif?.classList.remove('show-pop-up');
        pop_up_signup?.classList.add('show-pop-up');
    });

    safeAddListener(pop_up_closer_photo, 'click', () => {
        pop_up_notif_photo?.classList.remove('show-pop-up');
    });
});
