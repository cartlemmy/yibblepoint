window.addEventListener('load',function(){
	function getScrollTop(){
		return document.body.scrollTop || document.documentElement.scrollTop; 
	};

	var i, j, els, flIms = [], img, el, id, dLoad = [], w, h, wr, hr, sim;
	
	els = document.getElementsByTagName('*');
	
	function TMPCSS(el,css) {
		var n;
		for (n in css) {
			el._FL.css[n] = css[n]
		}
		$(el).css(css);
	}
	
	for (i = 0; i < els.length; i++) {
		el = els[i];
		if (el.getAttribute('data-fast-load')) {
			for (j = 0; j < window._FL_INFO.images.length; j++) {
				if (window._FL_INFO.images[j].id == el.id) {
					el._FL = window._FL_INFO.images[j];
					el._FL.css = {};
				}
			}
			
			if (el._FL) el.setAttribute('data-dload',el._FL.src);
		}
		
		if (el.getAttribute('data-ss')) {
			for (j = 0; j < window._FL_SS.images.length; j++) {
				sim = window._FL_SS.images[j];
				if (sim.id == el.id) {
					w = (sim.x2 - sim.x1) * 0.5;
					h = (sim.y2 - sim.y1) * 0.5;
					if (!$(el).css('width') && !$(el).css('height') || ($(el).css('width') == "1px" && $(el).css('height') == "1px")) {
						$(el).css({
							'width':w,
							'height':h
						});
					}
					wr = $(el).width() / w;
					hr = wr == 1 ? 1 : $(el).height() / h;

					$(el).css({
						'background-size':Math.ceil(window._FL_SS.w* 0.5*wr)+"px "+Math.ceil(window._FL_SS.h* 0.5*hr)+"px",
						"background-position":Math.floor(0-(sim.x1 * 0.5* wr))+"px "+Math.floor(0-(sim.y1 * 0.5* hr))+"px"
					});
				}
			}
			
		}
		
		if (el.getAttribute('data-dload')) dLoad.push(el);
	}	
	//$item->setAttribute("style","width:".(($image["x2"] - $image["x1"]) * 0.5)."px;height:".(($image["y2"] - $image["y1"]) * 0.5)."px;background-position:-".($image["x1"] * 0.5)."px -".($image["y1"] * 0.5)."px;".$item->getAttribute("style"));
	function doDLoad(el) {
		return; //TODO remove
		if (el.getAttribute('data-dload') == 'done') return;
		
		function loaded() {
			//if (el.getAttribute('data-fast-load')) {	
				el.style.backgroundImage = "none";
				el.style.backgroundSize = "";
				el.style.backgroundPosition = "";
			//} 
			el.style.backgroundColor = "";			
		}
		
		var src = el.getAttribute('data-dload').replace('@2x','');
		
		if (el.nodeName == "IFRAME") {
			if (window.$) {
				if ($(el).is(":visible")) {
					el.src = src;
				} else {
					setTimeout(function(){doDLoad(el)},200);
					return;
				}
			} else el.src = src;
		} else if (el.nodeName == "IMG") {
			el.src = src;
			if (el.complete) {
				loaded();
			} else el.addEventListener("load",loaded);
		} else {
			el.style.backgroundImage = "url('"+src+"')";
		}
		
		el.setAttribute('data-dload','done');
	}
						
	function scroll() {
		var i;
		if (window.$) {
			for (i = 0; i < dLoad.length; i++) {
				if (dLoad[i].getAttribute('data-dload') != 'done' && getScrollTop()+($(window).height()*1.1) > $(dLoad[i]).offset().top) { 
					doDLoad(dLoad[i]);					
				}
			}
		}
	}
	
	function refresh() {
		var w, h;
		for (i = 0; i < flIms.length; i++) {
			img = flIms[i];

			w = img.offsetWidth;
			h = img.offsetHeight;
			
			sx = w / (img._FL.x2 - img._FL.x1);
			sy = h / (img._FL.y2 - img._FL.y1);
			img.style.backgroundPosition = "-"+(img._FL.x1*sx)+"px -"+(img._FL.y1*sy)+"px";
			img.style.backgroundSize = (window._FL_INFO.w*sx)+"px "+(window._FL_INFO.h*sy)+"px";	
		}
		scroll();
	}
	
	refresh();
	
	window.addEventListener("resize",refresh);
	window.addEventListener("scroll",scroll);
	
	if (window._FL_PRELOAD) {
		src = window._FL_PRELOAD.shift();
		img = new Image();
		img.src = src+".jpg";
		
		function loaded() {
			cl = document.body.getAttribute('class') ? document.body.getAttribute('class').split(" ") : [];
			cl.push('fl-ready');
			document.body.setAttribute('class',cl.join(' '));
		}
		
		if (img.complete) {
			loaded();
		} else img.addEventListener("load",loaded);
	}
	
	setTimeout(function(){
		var i, cl, img, toLoad = 0, toLoadInline = 0, allLoad = 1;
		function allLoaded() {
			allLoad--;
			if (allLoad == 0) {
				if (!window.$) {
					while (el = dLoad.pop()) {
						doDLoad(el);
					}
				} else {
					for (i = 0; i < dLoad.length; i++) {
						if (dLoad[i].nodeName == "IFRAME") doDLoad(dLoad[i]);
					}
				}
				window._FL_COMPLETE = 1;
				scroll();		
			}
		}
		
		for (i = 0; i < flIms.length; i++) {
			(function(img){
				/*function loaded() {
					img.style.backgroundImage = "none";
					img.style.backgroundSize = "";
					img.style.backgroundPosition = "";
					toLoadInline--;
					if (toLoadInline == 0) allLoaded();
				}
				
				img.src = img._FL.src;
				toLoadInline ++;
				
				if (img.complete) {
					loaded();
				} else img.addEventListener("load",loaded);*/
			})(flIms[i]);
		}
		
		if (window._FL_PRELOAD) {
			allLoad ++;
			function swapPreload() {
				cl = document.body.getAttribute('class') ? document.body.getAttribute('class').split(" ") : [];
				if ((i = cl.indexOf('FL_BG')) != -1) {
					cl.splice(i,1);
				}
				document.body.setAttribute('class',cl.join(' '));
				allLoaded();
			}
			
			while (src = window._FL_PRELOAD.pop()) {
				(function(src){
					var img = new Image();
					
					img.src = sl.config.webRelRoot+src;
					toLoad ++;
					
					function loaded() {
						toLoad --;
						if (toLoad == 0) swapPreload();
					}
					
					if (img.complete) {
						loaded();
					} else img.addEventListener("load",loaded);
				})(src);
			}
		}
	},100);
});
