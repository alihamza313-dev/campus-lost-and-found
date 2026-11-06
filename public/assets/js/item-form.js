(function () {
    var box = document.getElementById('hidden-detail-box');
    var radios = document.querySelectorAll('input[name="report_type"]');
    if (!box || radios.length === 0) { return; }

    function sync() {
        var checked = document.querySelector('input[name="report_type"]:checked');
        box.hidden = !(checked && checked.value === 'found');
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();