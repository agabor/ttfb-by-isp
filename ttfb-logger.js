document.addEventListener("DOMContentLoaded", function () {
    if (window.performance) {
        var ttfb = performance.timing.responseStart - performance.timing.requestStart;
        var currentUrl = window.location.href;
	let userType = 'guest';
        if ( document.body.classList.contains( 'logged-in' ) ) {
            userType = 'logged_in';
        }

        // Send TTFB and URL to the server via AJAX
        fetch(ttfbLogger.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'log_ttfb',
                ttfb: ttfb,
                url: currentUrl,
		userType: userType,
                nonce: ttfbLogger.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('TTFB and URL logged successfully:', data);
            } else {
                console.error('Error logging data:', data);
            }
        })
        .catch(error => {
            console.error('AJAX error:', error);
        });
    }
});
