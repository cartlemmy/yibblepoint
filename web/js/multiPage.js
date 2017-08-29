function multiPage() {
	var padding = 40, oldHash, self = this, swipe = {"swiping":false,"snap":true,"startX":0,"lastX":0,"touchStartX":0,"velX":0}, history = [0];
	
	var width = 0, height = 0, curPage = 0, contEl,
		prevEl = document.getElementById('multi-page-prev'),
		nextEl = document.getElementById('multi-page-next'),
		navX = 0, navToX = 0;
	
	self.locked = [];
	
	function resize() {
		if (swipe.swiping) return;
		var x = 0;
		var top = document.getElementById('multi-page-nav').offsetHeight;

		width = 0;
		
		document.getElementById("multi-page-loading").style.display = "none";
		
		for (var i = 0; i < multiPageData.pages.length; i++) {
			var page = multiPageData.pages[i];
			if (!page.name) page.name = page.title.replace(/[^\w\d]+/gi,'-');
			
			var el = document.getElementById(page.id);
			el.style.display = "block";
			
			if (!width) {
				contEl = el.parentNode;
				//height = contEl.offsetHeight - top - contPad;
				width = contEl.offsetWidth;
				//dbg.innerHTML = "height = "+contEl.offsetHeight+" - "+top;
			}
			
			el.style.width = width+"px";
			//el.style.height = height+"px";
			el.style.left = x+"px";
			el.style.paddingTop = top+"px";
			x += width + padding;
		}
		
		oldHash = window.location.hash;
		refresh();
		hashChanged();
	};
	
	function updatePos() {
		if (swipe.swiping) return;
		
		if (swipe.velX) {
			navX += swipe.velX;

			swipe.velX *= 0.8;
			
			contEl.scrollLeft = Math.abs(navX);
			
			var np = Math.round(contEl.scrollLeft / (width + padding));
			
			if (Math.abs(swipe.velX) < 5 || np != curPage) {
				swipe.velX = 0;
				navTo(np);
			}
			return;
		}
		
		if (oldHash != window.location.hash) hashChanged();
		
		if (navToX != navX) {
			navX += (navToX - navX) / 4;
			if (Math.abs(navToX - navX) <= 1) {
				navX = navToX;
			}
			contEl.scrollLeft = Math.abs(navX);
			refresh();
		}
	};
	setInterval(updatePos,40);
	
	function refresh() {
		prevEl.style.visibility = curPage > 0 && !page(curPage).noNav && !page(curPage-1).noNav ? "" : "hidden";
		nextEl.style.visibility = curPage < multiPageData.pages.length - 1 && !page(curPage).noNav && !page(curPage+1).noNav ? "" : "hidden";
		document.getElementById('multi-page-nav').style.left = contEl.scrollLeft+"px";
	};
	
	function hashChanged() {
		oldHash = window.location.hash;
		var hash = window.location.hash.substr(1);
		
		for (var i = 0; i < multiPageData.pages.length; i++) {
			if (hash == multiPageData.pages[i].name) {
				navTo(i);
				return true;
			}
		}
		return false;
	};
	
	function page(i) {
		return i != -1 && multiPageData.pages[i] ? multiPageData.pages[i] : {};
	}
	
	function pageIndex(n) {
		if (typeof(n) == "number") return n;
		for (var i = 0; i < multiPageData.pages.length; i++) {
			if (n == multiPageData.pages[i].name) return i;
		}
		return -1;
	};
	
	function back() {
		if (history.length > 1) navTo(-1);
	};
	
	self.lockCheck = function(i) {
	
		if ((i === undefined || curPage < i) && self.locked.length) {
			var m = [], l, j;
			for (j = 0; j < self.locked.length; j++) {
				l = self.locked[j]
				m.push(l[1]);
				if (l[2]) l[2]();
			}
			alert(m.join("\n"));
			return true;
		}
		return false;
	};
	
	function navTo(i) {
		if (self.lockCheck(i)) return;
		
		if (i == -1) {
			history.pop();
			i = history.pop();
		} else {
			i = pageIndex(i);
			if (curPage == i) return;
			history.push(i);
		}
		if (window["_ON_MULTI_PAGE_LOAD"+i]) window["_ON_MULTI_PAGE_LOAD"+i]();
		
		var page = multiPageData.pages[i], hash = "#"+page.name;
		
		oldPage = curPage;
		curPage = i;
		navToX = i * (width + padding);
		
		if (window.location.href != hash) window.location.href = hash;
		
		if (self.navCallback) self.navCallback(multiPageData.pages[oldPage],multiPageData.pages[i]);
	};
	
	self.registerNavCallback = function(func) {
		self.navCallback = func;
		self.navCallback(null,multiPageData.pages[curPage]);
	};
	
	resize();
	window.addEventListener("resize",resize);
	setInterval(resize,1000);
	
	nextEl.addEventListener("click",function(){navTo(curPage+1)});
	prevEl.addEventListener("click",function(){navTo(curPage-1)});
	
	function touch(e) {
		var t = e.touches[0];
		switch (e.type) {
			case "touchstart":
				swipe.swiping = true;
				swipe.touchStartX = t.screenX;
				swipe.startX = contEl.scrollLeft;
				swipe.snap = true;
				break;
			
			case "touchmove":
				swipe.velX = swipe.lastX - t.screenX;
				if (Math.abs(t.screenX - swipe.touchStartX) > 20) swipe.snap = false;
				if (!swipe.snap) navX = contEl.scrollLeft = swipe.startX - (t.screenX - swipe.touchStartX);
				break;
				
			case "touchend":
				swipe.swiping = false;
				break;	
		}
		if (t) swipe.lastX = t.screenX;
	}
	
	//contEl.addEventListener('touchstart',touch);
	//contEl.addEventListener('touchmove',touch);
	//contEl.addEventListener('touchend',touch);
		
	self.navTo = function(i) {navTo(i);}
	self.back = function() {back();}
	
	self.navNext = function(){
		navTo(curPage+1);
	};
	
	self.navPrev = function(){
		navTo(curPage-1);
	};
	
	self.lock = function(id,msg,cb) {
		self.unlock(id);
		self.locked.push([id,msg,cb]);
	}
	
	self.unlock = function(id) {
		for (var i = 0; i < self.locked.length; i++) {
			if (self.locked[i][0] == id) {
				self.locked.splice(i,1);
				return;				
			}
		}
	}
		
	self.getCurPagetitle = function() {
		return multiPageData.pages[curPage].title;
	}
};
