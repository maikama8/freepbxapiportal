// Perfect Scrollbar fallback - loads from CDN
(function() {
    if (typeof PerfectScrollbar === 'undefined') {
        var script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/perfect-scrollbar/1.5.5/perfect-scrollbar.min.js';
        script.onload = function() {
            console.log('Perfect Scrollbar loaded from CDN');
        };
        document.head.appendChild(script);
    }
})();