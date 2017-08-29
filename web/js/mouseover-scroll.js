/* mouseover-scroll.js
 * Josh Merritt cartlemmy@gmail.com
 * 
 * Requires jQuery
 * Include just before </body> tag
 * 
 * Add class scroll-v for vertical scrolling, and scroll-h for horizontal scrolling
 */

(function(){
	function isTouchDevice() {
	   var el = document.createElement('div');
	   el.setAttribute('ontouchstart', 'return;');
	   return typeof el.ontouchstart === "function";
	}

	if (isTouchDevice()) {
		$('.scroll-v,.scroll-h').css({"overflow":"auto"});
	} else {
		$('.scroll-v,.scroll-h').css({"whiteSpace":"nowrap","overflow":"hidden"});
		$('.scroll-v').mousemove(function(e){
			var t = $(e.delegateTarget), pad = Math.round(t.height() * 0.05);
			t[0].scrollTop = Math.round(((Math.min(t.height()-pad,Math.max(pad,e.pageY-t.offset().top)) - pad) / (t.height() - pad * 2))*(t[0].scrollHeight-t.height()));
		});
		
		$('.scroll-h').mousemove(function(e){
			var t = $(e.delegateTarget);
			t[0].scrollLeft = Math.round(((e.pageX-t.offset().left)/t.width())*(t[0].scrollWidth-t.width()));
		});
	}
})();
