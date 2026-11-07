(function () {
    var main = document.getElementById('gallery-main');
    if (!main) { return; }
    var thumbs = document.querySelectorAll('.gallery-thumb');

    thumbs.forEach(function (a) {
        a.addEventListener('click', function (event) {
            event.preventDefault();
            main.src = a.getAttribute('href');
            main.alt = a.getAttribute('data-alt') || main.alt;
            thumbs.forEach(function (t) { t.removeAttribute('aria-current'); });
            a.setAttribute('aria-current', 'true');
        });
    });
})();