chrome.app.runtime.onLaunched.addListener(function() {
	chrome.app.window.create('notifications.html', {
		'outerBounds': {
			'width': 400,
			'height': 500
		}
	});
	
});

