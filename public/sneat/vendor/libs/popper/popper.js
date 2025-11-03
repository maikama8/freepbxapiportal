// Popper.js fallback - loads from CDN
(function() {
    if (typeof Popper === 'undefined') {
        var script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.11.8/umd/popper.min.js';
        script.onload = function() {
            console.log('Popper.js loaded from CDN');
        };
        document.head.appendChild(script);
    }
})();