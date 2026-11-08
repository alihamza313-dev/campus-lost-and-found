(function () {
    'use strict';

    document.documentElement.classList.add('js');

    // Confirm dangerous actions and stop double submits.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var button = event.submitter;
        var message = (button && button.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
            return;
        }
        setTimeout(function () {
            form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
        }, 0);
    });

    // Re-enable buttons when the browser's Back button restores the page.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            document.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = false; });
        }
    });

    // Mobile menu.
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('site-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Close the account menu when clicking elsewhere or pressing Escape.
    var menu = document.querySelector('.user-menu');
    if (menu) {
        document.addEventListener('click', function (e) {
            if (menu.open && !menu.contains(e.target)) { menu.open = false; }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && menu.open) { menu.open = false; menu.querySelector('summary').focus(); }
        });
    }

    // Show / hide password.
    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        if (input.closest('.input-wrap')) { return; }
        var wrap = document.createElement('div');
        wrap.className = 'input-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pw-toggle';
        btn.textContent = 'Show';
        btn.setAttribute('aria-label', 'Show password');
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
        wrap.appendChild(btn);
    });
})();
