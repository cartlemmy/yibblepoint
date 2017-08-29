(function(){
	window.closePopup = function(){
		var p = window.parent && window.parent.$("#popup-cont").length ? window.parent.$ : $;
		p('body').css('overflow','');

		if (p("#popup-cont").is(":visible")) {
			p("#popup-cont").slideUp();
		} else {
			var l = window.location.href.split('/');
			if (l[l.length-1]=='') l.pop();
			l.pop();
			window.location.href = l.join('/');
		}
	};
	
	$('#popup-cont .close, .popup-page-hide').click(window.closePopup);
	
	$('a').each(function(i,el){
		if (el.target == "popup-frame") {
			$(el).click(function(e){
				var p = [], l;
				
				e.preventDefault();
				l = el.href.split('?');
				if (l.length > 1) {
					l[1] = l[1].split('&');
					for (var i = 0; i < l[1].length; i++) {
						if (l[1][i] != "frame=1") p.push(l[1][i]);
					}
				}
				
				if ($( window ).width() <= 768) {
					window.location.href = l[0] + (p.length ? "?"+p.join("&") : "");
				} else {
					p.push("frame=1");
					$("#popup-frame")[0].src = "about:blank";
					$('body').css('overflow','hidden');
					$("#popup-cont").slideDown();
					setTimeout(function(){
						$("#popup-frame")[0].src = l[0]+"?"+p.join("&");
					},200);
				}
				return false;
			});
		}
	});
})();
