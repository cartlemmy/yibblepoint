(function(){
	
	function ts() {
		return (new Date()).getTime();
	}
		
	function raf(cb) {
		if (window.requestAnimationFrame) {
			window.requestAnimationFrame(cb);
		} else if (window.webkitRequestAnimationFrame) {
			window.webkitRequestAnimationFrame(cb);
		} else {
			setTimeout(cb,30);
		}
	}
		
	var controls = ["prev","next"];
	$('.bg-gallery').each(function(i,bgGallCont){
		
		var self = {}, i, images = [], curIm = 0, xTarg = 0, x = 0,
			newTs, arTimer = null, lastTs = ts(), animating = false,
			onStack = {"nav":[]}, width = $(bgGallCont).width(), ilm1,
			opts = ['data-gallery-auto-rotate'], controlEl = {},
			noLoop = !!bgGallCont.getAttribute('data-bg-gallery-no-loop'),
			pages = [
				bgGallCont.getAttribute('data-bg-gallery-prev-page'),
				bgGallCont.getAttribute('data-bg-gallery-next-page')
			];
			
		
		
		for (var i in opts) {
			self[(function(n){
				var i;
				n = n.split("-");
				n.shift();
				for (i = 1; i < n.length; i++) {
					n[i] = n[i].charAt(0).toUpperCase()+n[i].substr(1);
				}
				return n.join('');
			})(opts[i])] = bgGallCont.getAttribute(opts[i]);
		}
		
		if (self.galleryAutoRotate) arTimer = setInterval(autoRotate,Number(self.galleryAutoRotate) * 1000);
		
		$(bgGallCont).find('.bg-gallery-img').each(function(i,im){
			im.style.display = i == curIm ? "block" : "none";
			im.bggX = i;
			images.push(im);
		});
		
		if (!images.length) return;
		
		ilm1 = images.length - 1;
		
		function begin() {
			if (animating) return;
			animating = true;
			lastTs = ts();
			animate();
		}
		
		function pfix(x) {
			while (x < -1) {
				x += images.length;
			}
			while (x > ilm1) {
				x -= images.length;
			}
			return x;
		}
		
		function animate() {
			var i, ix;
			newTs = ts();
						
			var D = Math.min(1,Math.max(1,newTs - lastTs)/200);
			
			x += (xTarg - x) * D;
			
			if (Math.abs(x - xTarg) < 0.0005) {
				x = xTarg;
				animating = false;
			}
			
			for (i = 0; i < images.length; i++) {
				ix = Math.round( pfix(images[i].bggX - x) * width);

				images[i].style.display = "block";
				images[i].style.left = ix+"px";
			}
			
			if (!animating) {
				while (xTarg < 0) {
					xTarg += images.length;
				}
				while (xTarg >= images.length) {
					xTarg -= images.length;
				}
				x = xTarg;
				return;
			}
			
			lastTs = newTs;
			
			raf(animate);
		}
		
		function nav(dir) {
			if (noLoop) {
				if (dir == -1 && xTarg == 0) {
					if (pages[0]) window.location.href = pages[0];
					return;
				}
				
				if (dir == 1 && xTarg >= images.length - 1) {
					if (pages[1]) window.location.href = pages[1];
					return;
				}
			}
			
			if (arTimer) {
				clearInterval(arTimer);
				arTimer = setTimeout(autoRotate,10000);
			}
			xTarg += dir;
			refreshNav();
			begin();
			trigger("nav",dir);
			if (window.yibUIRefresh) window.yibUIRefresh();
		}
		
		function autoRotate() {
			xTarg ++;
			begin();
			trigger("nav",1);
		}
		
		function trigger(n,p) {
			var i, im = (xTarg+images.length)%images.length;
			for (i = 0; i < onStack[n].length; i++) {
				onStack[n][i](p,im,images[im]);
			}
		}
		
		function refreshNav() {
			if (noLoop) {
				if (xTarg == 0 && !pages[0]) {
					$(controlEl.prev).stop().fadeOut();
				} else {
					$(controlEl.prev).stop().fadeIn();
				}
				if (xTarg == images.length - 1 && !pages[1]) {
					$(controlEl.next).stop().fadeOut();
				} else {
					$(controlEl.next).stop().fadeIn();
				}
			}
		}
		
		window.bgGalleryAddListener = function(n,cb) {
			if (!onStack[n]) return;			
			onStack[n].push(cb);
		};
		
		var control = {
			"prev":function(){nav(-1)},
			"next":function(){nav(1)}
		};
		
		$(bgGallCont).find('.bg-gallery-control').each(function(i,el){
			for (i = 0; i < controls.length; i++) {
				if (el.className.indexOf(controls[i]) != -1) {
					controlEl[controls[i]] = el;
				}
			}
			$(el).click(function(){
				for (i = 0; i < controls.length; i++) {
					if (el.className.indexOf(controls[i]) != -1) {
						control[controls[i]]();
					}
				}
			});
		});
		refreshNav();
	});
})();
