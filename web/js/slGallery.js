window.slDPR = window.devicePixelRatio ? window.devicePixelRatio : 1;

function slSwipeGallery(id, info) {
	if (!info.images.length) return;
	
	var contEl = document.getElementById(id), x = 0, ssTimer, fromImg = -1,
		curImg = 0, targX = -1, velX = 0, 
		swipe = {"swiping":false,"snap":true,"startX":0,"lastX":0,"touchStartX":0,"velX":0};

	contEl.scrollLeft = x;
	
	function absBounds(n,lim) {
		while (n < 0) { n += lim; }
		return n % lim;
	}

	function getXForImage(im) {
		var x = getXForRealImage(im);
		return x + info.width * Math.floor(im / info.imgWHX.length);
	}
	
	function getXForRealImage(im) {
		im = absBounds(im,info.imgWHX.length);
		return Math.round((info.imgWHX[im][2] + (info.imgWHX[im][0] / 2)) - (contEl.offsetWidth / 2)) + 1;
	}
	
	function getImgForX(x) {
		var bestDist = 10000, bestIm = -1, dist = 0;
		for (var i = 0; i < 10; i++) {
			dist = Math.abs(x - getXForImage(curImg - i - 1));
			if (dist < bestDist) {
				bestDist = dist;
				bestIm = curImg - i - 1;
			}
			dist = Math.abs(x - getXForImage(curImg + i));
			if (dist < bestDist) {
				bestDist = dist;
				bestIm = curImg + i;
			}
		}
		return bestIm;
	}
	
	function nav(dir) {
		navToImage(curImg + dir);
		resetSlideshow();
	}
	
	function navToImage(im) {
		fromImg = curImg;
		curImg = im;
		scrollToPos(getXForImage(curImg));
	};
	
	function setVel(vx) {
		velX = vx;
	}
	
	function setPos(x) {
		contEl.scrollLeft = Math.round(absBounds(x,info.width));
	}
	
	function ts() {
		return (new Date()).getTime() / 1000;
	}
	
	var lastTs = ts();
	function updatePos() {
		var thisTs = ts(), T = Math.min(0.25,thisTs - lastTs);
		if (targX != -1) {
			setVel((targX - x) / 10);
			if (Math.abs(velX) < 0.1) {
				targX = x = absBounds(targX,info.width);
				curImg = absBounds(curImg,info.imgWHX.length);
				setVel(0);
			}
		}
		
		if (Math.abs(velX) > 0  && !swipe.swiping) {
			x += velX * T * 50;
			setPos(x);
			velX *= 0.9;
			if (Math.abs(velX) < 1) velX = 0;
		} else if (!swipe.swiping && Math.abs(velX) < 0.5 && (curImg != getImgForX(x) || x != getXForImage(curImg))) {
			navToImage(getImgForX(x));
		}
		
		lastTs = thisTs;
		setTimeout(updatePos,30);
	};
	
	setTimeout(updatePos,1000);
	
	function scrollToPos(x) {		
		targX = x;
	};
		
	function resetSlideshow() {
		if (ssTimer) clearTimeout(ssTimer);
		ssTimer = setTimeout(function(){
			nav(1);
		},6000);
	};
	
	document.getElementById('gallery-prev').addEventListener('click',function(){
		nav(-1);
	});
	
	document.getElementById('gallery-next').addEventListener('click',function(){
		nav(1);
	});
	
	function touch(e) {
		var t = e.touches[0];
		switch (e.type) {
			case "touchstart":
				swipe.swiping = true;
				swipe.touchStartX = t.screenX;
				swipe.startX = contEl.scrollLeft;
				swipe.snap = true;
				swipe.velX = 0;
				targX = -1;
				resetSlideshow();
				break;
			
			case "touchmove":
				swipe.velX = swipe.lastX - t.screenX;
				setVel(swipe.velX);
				x = swipe.startX - (t.screenX - swipe.touchStartX);
				setPos(x);
				//if (Math.abs(t.screenX - swipe.touchStartX) > 20) swipe.snap = false;
				//if (!swipe.snap) navX = contEl.scrollLeft = swipe.startX - (t.screenX - swipe.touchStartX);
				resetSlideshow();
				break;
				
			case "touchend":
				swipe.swiping = false;
				break;	
		}
		if (t) swipe.lastX = t.screenX;
	}
	
	var galNav = document.getElementById('gallery-nav');
	galNav.addEventListener('touchstart',touch);
	galNav.addEventListener('touchmove',touch);
	galNav.addEventListener('touchend',touch);
	
	resetSlideshow();
};

function slGridGallery(id, initialData) {
	var self = this, resizeTimer, positionTimer, origTitle = document.title;
	self.id = id;
	self.contEl = document.getElementById(id);
	
	self.scroller = new momehscroll(self.contEl);
	
	self.images = [];
	self.fadingImages = [];
	self.lHash = "";
	self.overlay = null;
	self.loadQueue = [];
	self.preloaded = !initialData.preloaderSrcWeb;
	
	if (!self.preloaded) {
		function preloaderLoaded() {
			var f;
			while (f = self.loadQueue.pop()) {
				f();
			}
			self.preloaded = true;			
		};
		
		var pi = document.createElement("img");
		pi.src = initialData.preloaderSrcWeb;
		pi.style.width = "0px";
		pi.style.height = "0px";
		document.body.appendChild(pi);
		
		if (pi.complete) {
			preloaderLoaded();
		} else {
			pi.addEventListener("load",preloaderLoaded);
		}
	}
	
	self.resize = function() {
		var i, j, w, h, scale, image, shortestRow, shortestRowWidth;
		self.width = self.contEl.offsetWidth;
		self.height = self.contEl.offsetHeight;
		
		self.rows = Math.max(1,Math.ceil(self.height / self.params.height));
		self.rowHeight = (self.height - (self.rows - 1) * self.params.padding) / self.rows;		
		self.rowX = [];
		for (i = 0; i < self.rows; i++) {
			self.rowX.push(0);
		}
		
		for (i = 0; i < self.images.length; i++) {
			shortestRow = shortestRowWidth = 200000;
			for (j = 0; j < self.rows; j++) {
				if (self.rowX[j] < shortestRowWidth) {
					shortestRowWidth = self.rowX[j];
					shortestRow = j;
				}
			}
			
			image = self.images[i];
			
			scale = self.rowHeight / image.nat_height;
			
			w = Math.round(image.nat_width * scale);
			h = Math.round(image.nat_height * scale);
			
			image.setSizeAndPosition(self.rowX[shortestRow],Math.round(shortestRow*(self.rowHeight+self.params.padding)),w,h);
			
			self.rowX[shortestRow] += w + self.params.padding;
		}
		self.positionCheck();
	}
	
	function ts() {
		return (new Date()).getTime() / 1000;
	}
	
	self.getImage = function(id) {
		for (i = 0; i < self.images.length; i++) {
			self.images[i].i = i;
			if (self.images[i].webFileName == id || self.images[i]._ID == id) return self.images[i];
		}
		return null;
	}
	
	self.showImage = function(image) {
		if (image = self.getImage(image)) {
			document.title = image.title + " - " + origTitle;
			self.scrollToPos(image.cx);
			if (!self.overlay) {
				self.overlay = new slGalleryOverlay({
					"gallery":self,
					"ajaxID":self.ajaxID,
					"galleryData":self.galleryData,
					"images":initialData.images,
					"params":self.params,
					"path":self.path,
					"ajaxID":self.ajaxID,
					"fromEl":image.el,
					"image":i
				});
			} else {
				self.overlay.navToImg(i);
			}
		}
	}
	
	var lastTs = ts();
	self.update = function() {
		if (window.location.hash != self.lHash) {
			self.lHash = window.location.hash;
			self.showImage(self.lHash.substr(1));
		}
		
		var newTs = ts();
		var i, removed = false;
		for (i = 0; i < self.fadingImages.length; i++) {
			if (self.fadingImages[i] && self.fadingImages[i].fade(newTs - lastTs)) {
				self.fadingImages[i] = null;
			}
		}
		
		lastTs = newTs;
		setTimeout(self.update,30);
	}
	
	self.scrollToPos = function(x) {
		self.targX = Math.min(self.contEl.scrollWidth-self.width,Math.max(0,Math.round(x-self.width/2)));
		self.contEl.scrollLeft = self.targX;
	};
		
	self.detailLoaded = function(image) {
		self.fadingImages.push(image);
	}
		
	self.positionCheck = function() {
		if (positionTimer) clearTimeout(positionTimer);
		positionTimer = setTimeout(function(){
			var x = self.contEl.scrollLeft;
			var image;
			for (i = 0; i < self.images.length; i++) {
				image = self.images[i];
				var dist = Math.abs(image.cx-(x+self.width/2));
				image.loadDetail(dist < self.width);
			}
		},50);
	};
	
	self.setGallery = function(data) {
		var im, i, n;
		while (im = self.images.pop()) {
			im.destroy();
		}
		
		for (var n in data) {
			switch (n) {
				case "images":break;
	
				default:
					self[n] = data[n];
					break;
			}
		}
		for (i = 0; i < data.images.length; i++) {
			data.images[i].gallery = self;
			self.images.push(new slGridGalleryImage(data.images[i]));
		}
		self.resize();
	};
	
	if (initialData) self.setGallery(initialData);
		
	window.addEventListener("resize",function(){
		if (resizeTimer) clearTimeout(resizeTimer);
		resizeTimer = setTimeout(self.resize,250);
	});
	
	self.contEl.addEventListener("scroll",self.positionCheck);
	self.update();
}

function slGridGalleryImage(o) {
	var self = this, n;
	self.opacity = 0;
	
	self.fade = function(T) {
		self.opacity = Math.min(1,self.opacity + T * 2);
		self.imEl.style.opacity = self.opacity;
		return self.opacity == 1;
	}
	
	self.init = function() {
		self.el = document.createElement("div");
		self.el.className = "sl-grid-gallery-image";
		self.el.addEventListener("click",function(){
			window.location.href = "#"+self.webFileName;
		});
		
		self.imEl = document.createElement("img");
		self.el.appendChild(self.imEl);
		
		self.gallery.contEl.appendChild(self.el);
	};
	
	self.loadDetail = function(on) {
		function loaded() {
			self.gallery.detailLoaded(self);
		}
		
		function load() {
			self.imEl.src = window.slDPR >= 1.5 ? self.image.replace(/\.(jpeg|jpg|png|gif)/,'@2x.$1') : self.image;
			if (self.imEl.complete) {
				loaded();
			} else self.imEl.addEventListener("load",loaded());
		}
		
		if (on && !self.detail) {
			self.detail = true;
			if (self.gallery.preloaded) {
				load();
			} else self.gallery.loadQueue.push(load);
		}
	};
	
	self.setSizeAndPosition = function(x,y,w,h) {
		self.x = x;
		self.y = y;
		self.w = w;
		self.h = h;
		self.cx = x + (w/2);
		self.cy = y + (h/2);
		
		self.el.style.left = x+"px";
		self.el.style.top = y+"px";
		self.imEl.style.width = self.el.style.width = w+"px";
		self.imEl.style.height = self.el.style.height = h+"px";
		if (self.gallery.params.createPreloader) {
			var scale = self.gallery.params.preloaderCompression * (self.h / self.nat_height);
			self.el.style.backgroundSize = Math.round(self.gallery.preloaderSize[0]*scale)+"px "+Math.round(self.gallery.preloaderSize[1]*scale)+"px";
			self.el.style.backgroundPosition = "-"+Math.round(self.pl[0]*scale)+"px -"+Math.round(self.pl[1]*scale)+"px";
		}
	};
	
	for (n in o) {
		switch (n) {				
			case "width": case "height":
				self["nat_"+n] = o[n];
				break;
		
			default:
				self[n] = o[n];
				break;
		}
	}
	
	self.detail = false;
	self.init();
}

function slGalleryOverlay(o) {
	var self = this, resizeTimer;
	self.visible = false;
	self.opacity = 1;
	self.swipeStartImg = self.curImg = 0;
	
	function d(p,t,c) {
		var el = document.createElement(t);
		if (c) el.className = c;
		p.appendChild(el);
		return el;
	}
	
	self.navToImg = function(i, noAnim, internal) {
		if (i === undefined) return;
		i = Math.max(0,Math.min(self.images.length-1,i));
		
		if ((!internal && self.show()) || noAnim) {
			self.scroller.setVel(0,0);
			self.scroller.setPos(i * self.fullWidth,0,true);
		} else {
			self.scroller.scrollToPos(i * self.fullWidth,0);
		}
		self.curImg = i;
	};
	
	self.show = function() {
		return self.showOrHide(true);
	};
	
	self.hide = function() {
		return self.showOrHide(false);
	};
	
	function ts() {
		return (new Date()).getTime();
	}
	
	var animTimer, lastAnim = ts();
	function animate(T) {
		if (animTimer) clearTimeout(animTimer);
		if (T) {
			self.opacity += (T / 1000) * (self.visible ? 1 : -1);
			if (!self.visible) {
				self.hideY += T;
				self.scroller.setPos(false,Math.round(self.hideY),true);
			} else {
				self.scroller.setPos(self.curImg * self.fullWidth,0,true);
			}
			
			if (self.opacity > 0) self.overlayEl.style.display = "";
			
			if (self.visible && self.opacity >= 1) {
				self.overlayEl.style.opacity = self.opacity = 1;
				self.detailCheck(self.scroller.getX());
				return;
			} else if (!self.visible && self.opacity <= 0) {
				self.overlayEl.style.opacity = self.opacity = 0;
				self.overlayEl.style.display = "none";
				return;
			}
			self.overlayEl.style.opacity = self.opacity;
		} else lastAnim = ts();
		
		animTimer = setTimeout(function(){
			var t = ts();
			animate(t - lastAnim);
			lastAnim = t;
		},30);
	};
	
	self.showOrHide = function(v) {
		var oldV = self.visible;
		self.visible = v;
		if (self.gallery) self.gallery.scroller.active = !v;
		animate();
		return self.visible != oldV;		
	}
	
	self.scrollCB = function(t) {
		switch (t) {
			case "start":
				self.swipeStartImg = self.curImg;
				break;
		}
	}
	
	self.positionCheck = function(x,y,vx,vy) {
		if (!self.scroller && !self.visible) return;
		
		if (y > 0) {
			self.scroller.scrollToPos(false,0);
			if (y*(1+vy) > self.height*0.5) {
				self.hideY = y;
				self.hide();
			}
		}
		
		var i;
		if (self.gallery && self.scroller.fps > 20) {
			i = Math.max(0,Math.floor(x / self.fullWidth));
			var pos = (x / self.fullWidth) - i;
			self.gallery.scrollToPos(Math.round(self.gallery.images[i].cx*(1-pos) + self.gallery.images[i+1].cx*pos));
		}
		
		var checkTarg = vx != 0 && !self.scroller.swiping();
		
		var targImNum = Math.max(0,Math.min(self.swipeStartImg+(vx < 0 ? -1 : 1),self.gallery.images.length-1));
		var targX = targImNum * self.fullWidth;

		if ((checkTarg && vx < 0 && x < targX) || (checkTarg && vx > 0 && x > targX)) {
			self.scroller.setVel(0,0);
			self.scroller.setPos(targX,0,true);
			vx = 0;	
		}
		
		if (Math.abs(vx) < 0.2) {
			self.navToImg(Math.round(x / self.fullWidth),false,true);
			self.detailCheck(x);
			if (Math.abs(vx) == 0) window.location.href = "#"+self.images[self.curImg].olImage.webFileName;
		}
	};
	
	self.detailCheck = function(x) {
		var image;
		for (i = 0; i < self.images.length; i++) {
			image = self.images[i].olImage;
			var dist = Math.abs(image.cx-(x+self.width/2));
			image.loadDetail(dist < (image.detail ? self.width * 4 : self.width * 2));
		}
	}
	
	
	self.resize = function() {
		var i, w, h, image, scale;
		self.width = self.overlayEl.offsetWidth;
		self.fullWidth = self.width + 20;
		
		self.height = self.overlayEl.offsetHeight;
		
		self.contEl.style.width = self.width+"px";
		self.contEl.style.height = self.height+"px";
		
		self.tallEl.style.height = (self.height * 3)+"px";
		
		self.imagesWidth = 0;
		for (i = 0; i < self.images.length; i++) {
			image = self.images[i];
			
			if (!image.ol) {
				image.ol = self;
				image.olImage = new slGalleryOverlayImage(image);
			}
			
			image.olImage.setSizeAndPosition(self.imagesWidth,0,self.width,self.height);
			self.imagesWidth += self.fullWidth;
		}
		self.navToImg(self.curImg,true,true);
	}
	
	self.init = function() {
		self.overlayEl = d(document.body,"div","sl-gallery-overlay");

		self.contEl = d(self.overlayEl,"div","cont");
		
		self.tallEl = d(self.contEl,"div","tall");
		
		self.scroller = new momehscroll(self.contEl,self.positionCheck,self.scrollCB);
		
		self.resize();
		window.addEventListener("resize",function(){
			if (resizeTimer) clearTimeout(resizeTimer);
			resizeTimer = setTimeout(self.resize,250);
		});
		if (self.image) self.navToImg(self.image,true,false);
		self.detailCheck(self.scroller.getX());
	}	
	
	for (n in o) {
		self[n] = o[n];
	}
	self.init();
};

function momehscroll(el,posCB,cb) {
	var self = this;
	el.style.overflow = "hidden";
	self.fps = 0;
	self.swipeAccel = 1;
	self.active = true;
	
	var 
		x = el.scrollLeft, y = el.scrollTop,
		targX = -1, velX = 0, 
		targY = -1, velY = 0, 
		swipe = {
			"swiping":false,
			"startX":0,"lastX":0,"touchStartX":0,"velX":0,
			"startY":0,"lastY":0,"touchStartY":0,"velY":0
		}, supportsTouch = false, velThresh = 0.01;
	
	var lastTouchTS = ts();
	function touch(e) {
		
		var thisTs = ts(), T = thisTs - lastTouchTS;
		
		var isTouch = e.type.substr(0,5) == "touch";
		var t = isTouch ? e.touches[0] : e;
		if (isTouch) supportsTouch = true;
		
		if (supportsTouch && !isTouch) return;
				
		switch (e.type) {
			case "touchstart": case "mousedown":
				swipe.swiping = true;
				
				swipe.touchStartX = t.screenX;
				swipe.startX = el.scrollLeft;
				swipe.velX = 0;
				targX = -1;
				
				swipe.touchStartY = t.screenY;
				swipe.startY = el.scrollTop;
				swipe.velY = 0;
				targY = -1;
				if (cb) cb("start");
				break;
			
			case "touchmove": case "mousemove":
				if (!swipe.swiping) break;
				swipe.velX = swipe.lastX - t.screenX;
				swipe.velY = swipe.lastY - t.screenY;
				self.setVel(swipe.velX / T * self.swipeAccel,swipe.velY / T * self.swipeAccel);
				x = swipe.startX - (t.screenX - swipe.touchStartX) * self.swipeAccel;
				y = swipe.startY - (t.screenY - swipe.touchStartY) * self.swipeAccel;
				self.setPos(x,y);
				e.preventDefault();
				if (cb) cb("move");
				break;
				
			case "touchend": case "mouseup": case "mouseout":
				swipe.swiping = false;
				if (posCB) posCB(x,y,velX,velY);				
				if (cb) cb("end");
				break;	
		}
		if (t) {
			swipe.lastX = t.screenX;
			swipe.lastY = t.screenY;
			lastTouchTS = thisTs;
		}
	}
	
	self.swiping = function() {
		return swipe.swiping;
	}
	
	self.setVel = function(vx, vy) {
		if (vx !== false) velX = vx;
		if (vy !== false) velY = vy;
	}
	
	self.getX = function() { return x; }
	self.getY = function() { return y; }
	
	self.setPos = function(sx, sy, clearTarg) {
		var h = el.scrollHeight - el.offsetHeight, w = el.scrollWidth - el.offsetWidth;
		
		if (sx !== false) {
			if (sx < 0) { sx = 0; velX = 0; }
			if (sx > w) { sx = w; velX = 0; }
			el.scrollLeft = Math.round(sx);
			if (!velX || clearTarg) x = Math.round(sx);
		}
		
		if (sy !== false) {
			if (sy > h) { sy = h; velY = 0; }
			if (sy < 0) { sy = 0; velY = 0; }
			el.scrollTop = Math.round(sy);
			if (!velY || clearTarg) y = Math.round(sy);
		}

		if (clearTarg) {
			velX = velY = 0;
			targX = targY = -1;
		}
		
	}
	
	self.scrollToPos = function(sx,sy) {
		if (sx !== false && sx != el.scrollLeft) targX = sx;
		if (sy !== false && sy != el.scrollTop) targY = sy;
	}
	
	function ts() {
		return (new Date()).getTime();
	}
	
	var lastTs = ts();
	function updatePos() {
		var thisTs = ts(), T = thisTs - lastTs, hit = false, moved = false;
		
		if (el.style.display == "none" || !self.active) {
			lastTs = thisTs;
			setTimeout(updatePos,200);
			return;
		}
		
		self.fps = 1000 / T;

		if (swipe.swiping) targX = targY = -1;
		
		if (targX != -1) {
			if (Math.abs(x - targX) < 1) {
				hit = true;
				x = targX;
				targX = -1;
				velX = 0;
			} else {
				x += (targX - x) / Math.max(1,100/T);
				velX = (targX - x) / 100;
			}
			moved = true;
		}
		
		if (targY != -1) {
			
			if (Math.abs(y - targY) < 1) {
				hit = true;
				y = targY;
				targY = -1;
				velY = 0;
			} else {
				y += (targY - y) / Math.max(1,100/T);
				velY = (targY - y) / 100;
			}
			moved = true;
		}
		
		if (Math.abs(velX) < velThresh) velX = 0;
		if (Math.abs(velY) < velThresh) velY = 0;
		
		if (Math.max(Math.abs(velX),Math.abs(velY)) > 0 && !swipe.swiping) {
			if (targX == -1) x += velX * T;
			if (targY == -1) y += velY * T;
			velX *= 0.9;
			velY *= 0.9;
			hit = true;
			moved = true;		
		}

		if (moved) {
			self.setPos(x,y);
		}
		
		if (hit && posCB) posCB(x,y,velX,velY);

		lastTs = thisTs;
		setTimeout(updatePos,Math.max(1,(30 - (ts() - thisTs))));
	};
	
	updatePos();
		
	el.addEventListener('touchstart',touch);
	el.addEventListener('touchmove',touch);
	el.addEventListener('touchend',touch);
	
	el.addEventListener('mousedown',touch);
	el.addEventListener('mouseup',touch);
	el.addEventListener('mousemove',touch);
	el.addEventListener('mouseout',touch);
};

function slGalleryOverlayImage(o) {
	var self = this, n;
	self.init = function() {
		self.detail = false;
		self.el = document.createElement("div");
		self.el.className = "sl-grid-gallery-overlay-image";
		self.el.addEventListener("click",function(){
			window.location.href = "#"+self.webFileName;
		});
		
		self.imContEl = document.createElement("div");
			self.imEl = document.createElement("img");
			self.imContEl.appendChild(self.imEl);
	
		self.el.appendChild(self.imContEl);
	
		self.ol.contEl.appendChild(self.el);
	};
	
	self.loadDetail = function(on) {				
		if (self.detail != on) {
			if (on) {
				self.imEl.src = window.slDPR >= 1.5 ? self.fullImage.replace(/\.(jpeg|jpg|png|gif)/,'@2x.$1') : self.fullImage;
			} else {
				self.imEl.src = "";
			}
			self.detail = on;
		}
	};
	
	self.setSizeAndPosition = function(x,y,w,h) {
		self.x = x;
		self.y = y;
		self.w = w;
		self.h = h;
		
		var contRat = self.ol.width / self.ol.height,
			imgRat = self.nat_width / self.nat_height;
		
		if (contRat > imgRat) {
			self.imW = Math.round(self.ol.height * imgRat);
			self.imH = self.ol.height;
		} else {
			self.imW = self.ol.width;
			self.imH = Math.round(self.ol.width / imgRat);
		}
		
		self.imContEl.style.left = Math.round((self.ol.width - self.imW) / 2)+"px";
		self.imContEl.style.top = Math.round((self.ol.height - self.imH) / 2)+"px";
		self.imEl.style.width = self.imContEl.style.width = self.imW+"px";
		self.imEl.style.height = self.imContEl.style.height = self.imH+"px";
		
		if (self.gallery.params.createPreloader) {
			var scale = self.gallery.params.preloaderCompression * (self.imH / self.nat_height);
			self.imContEl.style.backgroundSize = Math.round(self.gallery.preloaderSize[0]*scale)+"px "+Math.round(self.gallery.preloaderSize[1]*scale)+"px";
			self.imContEl.style.backgroundPosition = "-"+Math.round(self.pl[0]*scale)+"px -"+Math.round(self.pl[1]*scale)+"px";
		}
		
		self.cx = x + (w/2);
		self.cy = y + (h/2);
		
		self.el.style.left = x+"px";
		self.el.style.top = y+"px";
		self.el.style.width = w+"px";
		self.el.style.height = h+"px";
	};
	
	for (n in o) {
		switch (n) {				
			case "width": case "height":
				self["nat_"+n] = o[n];
				break;
		
			default:
				self[n] = o[n];
				break;
		}
	}
	
	self.detail = false;
	self.init();
}
