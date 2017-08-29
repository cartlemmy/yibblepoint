

(function(){
	if (!window._LOCK_SCROLL) window._LOCK_SCROLL = 0;
	
	var i, events = ['canplay','load'], cbs = [], toComplete = 0,
		scrollBlock, listeners = [];
	$(document.body).addClass('loading');
	
	function allLoaded() {
		$(document.body).removeClass('loading');
		$(document.body).addClass('loaded');
		
		if (ypLoader.preventScroll) {
			window._LOCK_SCROLL--;
			$("body").css("overflow", "");
			window.removeEventListener('scroll',scrollBlock,true);
		}
		
		while (cb = cbs.pop()) {
			cb();
		}
	}
	function itemComplete() {
		var cb;
		toComplete--;

		if (toComplete == 0) {
			allLoaded();
		}
	}
	
	var def = {
		addCallback:function(cb){
			cbs.push(cb);
		},
		preventScroll:false,
		onProgress:function(progress,total,percent){}
	};
	if (!window.ypLoader) window.ypLoader = {};
	
	for (var i in def) {
		if (window.ypLoader[i] === undefined) {
			window.ypLoader[i] = def[i];
		}
	}
	
	if (1) { // TODO: should be if (scrolled down)
		allLoaded(); return;
	}
	
	function setProgress(li,progress,total) {
		if (total !== undefined) listeners[li].total = total;
		listeners[li].progress = progress !== undefined ? progress : listeners[li].total;
		refreshProgress();
	}
	
	function refreshProgress() {
		var i, progress = 0,total = 0, left = [];
		for (i = 0; i < listeners.length; i++) {
			progress += Math.min(listeners[i].total,listeners[i].progress);
			total += listeners[i].total;
			if (listeners[i].progress < listeners[i].total) left.push(listeners[i].el.src);
		}
		if (total) ypLoader.onProgress(progress,total,Math.round(progress/total*10000)/100);
	}
	
	function addListener(el,event) {
		var total = (function(li){
			toComplete++;
			el.addEventListener(event,function(){
				setProgress(li);
				itemComplete();
			});
						
			return el.getAttribute('data-filesize')?Number(el.getAttribute('data-filesize')):10000;
		})(listeners.length);
		
		listeners.push({"el":el,"event":event,"progress":0,"total":total});
		refreshProgress();
	}
	
	$((function(){var rv=[];for (i = 0;i < events.length;i++){rv.push('.loader-on'+events[i])};return rv.join(",")})()).each(function(i,el){
		for (i = 0;i < events.length; i++){
			if (el.className.indexOf('loader-on'+events[i]) != -1) {
				addListener(el,events[i]);
			}
		}
	});
	
	$('.play-when-loaded').each(function(i,el){
		ypLoader.addCallback(function(){
			el.play();
			$(el).fadeIn();
		});
	});
	
	$('.show-when-loaded').each(function(i,el){
		ypLoader.addCallback(function(){
			$(el).fadeIn(function(){
				el.style.opacity = 1;
			});
		});
	});

		
	if (ypLoader.preventScroll) {
		$("body").css("overflow", "hidden");
		window._LOCK_SCROLL++;
		scrollBlock = function(e){
			if (getScrollTop() != 0) setScrollTop(0);
			e.preventDefault();
			e.stopPropagation();
		};
		window.addEventListener('scroll',scrollBlock,true);
	}
	
	$(document).ready(function(){
		var im, src;
		$('*').each(function(i,el){
			if (el.tagName == "SOURCE" || el.tagName == "VIDEO" || el.tagName == "SCRIPT" || el.tagName == "IFRAME") return;
			
			if (el.src && !el.complete && !el.getAttribute('data-fast-load') && !el.getAttribute('data-dload')) {
				addListener(el,'load');
			}
			if (el.style.backgroundImage && (im = el.style.backgroundImage.match(/url\(('|")?.+('|")?\)/))) {
				if (src = im[0].replace(/url\(('|")?(.*?)('|")?\)/,'$2')) {
					var img = new Image();
					img.src = src;
					
					if (!img.complete) addListener(img,'load');
				}
			}			
		});
	});
})();
