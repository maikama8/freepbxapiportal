// jQuery fallback - loads from CDN
(function() {
    if (typeof jQuery === 'undefined') {
        var script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js';
        script.onload = function() {
            console.log('jQuery loaded from CDN');
        };
        document.head.appendChild(script);
    }
})();