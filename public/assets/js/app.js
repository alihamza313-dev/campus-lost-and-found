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

// Re-enable buttons if the user comes back with the browser's Back button.
window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        document.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = false; });
    }
});