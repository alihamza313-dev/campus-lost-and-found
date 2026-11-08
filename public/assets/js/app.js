(function () {
    'use strict';

    document.documentElement.classList.add('js');

    var MAX_FILE_BYTES = 2 * 1024 * 1024;

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

    // Mobile menu: opens and closes, closes with Escape, resets when the window gets wide again.
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('site-nav');
    if (toggle && nav) {
        var setOpen = function (open) {
            nav.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        toggle.addEventListener('click', function () { setOpen(!nav.classList.contains('is-open')); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && nav.classList.contains('is-open')) { setOpen(false); toggle.focus(); }
        });
        window.addEventListener('resize', function () { if (window.innerWidth > 1040) { setOpen(false); } });
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

    // Character counter on limited text areas.
    document.querySelectorAll('textarea[maxlength]').forEach(function (area) {
        var max = parseInt(area.getAttribute('maxlength'), 10);
        if (!max) { return; }
        var note = document.createElement('p');
        note.className = 'counter';
        note.setAttribute('aria-hidden', 'true');
        area.insertAdjacentElement('afterend', note);
        var update = function () { note.textContent = area.value.length + ' of ' + max; };
        area.addEventListener('input', update);
        update();
    });

    // File inputs: reject files over 2 MB right away and preview chosen photos.
    document.querySelectorAll('input[type="file"]').forEach(function (input) {
        var error = document.createElement('p');
        error.className = 'field-error';
        error.setAttribute('role', 'alert');
        error.hidden = true;
        var preview = document.createElement('div');
        preview.className = 'upload-preview';
        input.insertAdjacentElement('afterend', preview);
        preview.insertAdjacentElement('afterend', error);

        input.addEventListener('change', function () {
            preview.innerHTML = '';
            error.hidden = true;
            var files = Array.prototype.slice.call(input.files || []);
            var tooBig = files.filter(function (f) { return f.size > MAX_FILE_BYTES; });
            if (tooBig.length) {
                error.textContent = tooBig[0].name + ' is larger than 2 MB. Choose a smaller file.';
                error.hidden = false;
                input.value = '';
                return;
            }
            files.forEach(function (f) {
                if (f.type.indexOf('image/') !== 0) { return; }
                var img = document.createElement('img');
                img.alt = 'Preview of ' + f.name;
                img.src = URL.createObjectURL(f);
                img.onload = function () { URL.revokeObjectURL(img.src); };
                preview.appendChild(img);
            });
        });
    });

    // Success messages disappear after a few seconds. Errors and warnings stay.
    document.querySelectorAll('main > .alert-success').forEach(function (a) {
        setTimeout(function () { a.hidden = true; }, 8000);
    });
})();