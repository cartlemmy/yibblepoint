sl.loadingOverlay = function(o) {
	var self = this;
	sl.initSlClass(this,"loading-overlay");

	self.init = function() {
		self.startTs = sl.unixTS(true);
		self.opacity = 0;
		self.animationTimer = setInterval(self.animate,100);
		self.overlayEl = sl.dg("",self.el.parentNode,"div",{
			"className":"loader-overlay",
			"style":{
				"left":self.el.offsetLeft+"px",
				"top":self.el.offsetTop+"px",
				"width":self.el.offsetWidth+"px",
				"height":self.el.offsetHeight+"px",
				"position":"absolute"
			}
		});
		
		var loaderPos = sl.dg("",self.overlayEl,"div",{
			"style":{
				"left":"50%",
				"top":"50%",
				"position":"absolute"
			}
		});
		
		var loaderCont = sl.dg("",loaderPos,"div",{});
		
		self.loaderEl = sl.dg("",loaderCont,"div",{
			"className":"loader"
		});
		
		self.loaderBarOuterEl = sl.dg("",loaderCont,"div",{
			"className":"loader-bar"
		});
		
		self.loaderBarEl = sl.dg("",self.loaderBarOuterEl,"div",{});
		
		self.loaderBarInfoEl = sl.dg("",loaderCont,"div",{
			"className":"loader-bar-info"
		});
	};
	
	self.animate = function() {
		self.opacity = Math.min(1,self.opacity + 0.05);
		self.overlayEl.style.opacity = self.opacity;
		
		self.loaderEl.className = "loader fr"+self.loaderAnimPos;
		
		self.loaderAnimPos ++;
		if (self.loaderAnimPos > 12) self.loaderAnimPos = 0;
		
	};
	
	self.loaded = function() {
		clearInterval(self.animationTimer);
		self.overlayEl.style.opacity = 0;
	};
	
	self.progress = function(loaded,ofTotal,message) {
		var pos = loaded / ofTotal, ts = sl.unixTS(true);
		
		if (self.lastTs) {
			var r = (ts - self.lastTs) / (pos - self.lastPos);
			if (self.rate.length < 40) {
				self.rate.push(r);
				self.rateTot += r;
			} else {
				self.rateTot += r - self.rate[self.rateCnt % 40];
				self.rate[self.rateCnt % 40] = r;
				self.rateCnt++;
			}
		}
		
		if (ts > self.startTs + 5 && self.rateCnt > 6) {
			if (self.useRate) {
				self.useRate += ((self.rateTot/40) - self.useRate)/20;
			} else {
				self.useRate = self.rateTot/40;
			}
		}
		
		self.lastPos = pos;
		self.lastTs = ts;
		
		var perc = Math.round(pos * 100)+"%";
		self.loaderBarOuterEl.style.display = "block";
		
		self.loaderBarEl.style.width = perc;
		self.loaderBarEl.innerHTML = "&nbsp "+perc+" ("+sl.shortNum(loaded)+" / "+sl.shortNum(ofTotal)+")";
		
		if (!message) message = "";
		
		if (self.useRate) {
			self.remaining.setValue(Math.round(self.useRate*(1-pos)));
			message += "<br />"+self.remaining.toString()+" remaining";
		}
		
		self.loaderBarInfoEl.innerHTML = message;		
	};
	
	self.destruct  = function() {
		if (self.overlayEl.parentNode) self.overlayEl.parentNode.removeChild(self.overlayEl);
	};
	
	self.setValues({
		"opacity":0,
		"loaderAnimPos":0,
		"rate":[],
		"useRate":0,
		"rateTot":0,
		"rateCnt":0,
		"lastPos":0,
		"lastTs":0,
		"startTs":0,
		"remaining":new sl.value({"type":"duration","minUnit":1}),
		"animationTimer":null
	});
	
	if (o) self.setValues(o);
	
	self.init();
};

sl.loaderBar = function(cont,valueType) {
	var self = this;
	
	self.loaderBarOuterEl = sl.dg("",cont,"div",{"className":"standalone-loader-bar"});
	
	self.loaderBarEl = sl.dg("",self.loaderBarOuterEl,"div",{"className":"bar"});
	self.loaderProgressTextEl = sl.dg("",self.loaderBarOuterEl,"div",{"className":"progress-text"});
	
	self.loaderBarInfoEl = sl.dg("",cont,"div",{"className":"standalone-loader-bar-info"});

	function sn(v) {
		return valueType == "bytes" ? sl.bytesFormat(v) : sl.shortNum(v);
	};
	
	self.progress = function(loaded,ofTotal,message) {
		var pos = loaded / ofTotal;
		
		var perc = Math.round(pos * 100)+"%";
		self.loaderBarOuterEl.style.display = "block";
		
		self.loaderBarEl.style.width = perc;
		self.loaderProgressTextEl.innerHTML = "&nbsp "+perc+" ("+sn(loaded)+" / "+sn(ofTotal)+")";
		
		if (!message) message = "";
				
		self.loaderBarInfoEl.innerHTML = message;	
	};
	
	self.destroy = function() {
		self.loaderBarOuterEl.parentNode.removeChild(self.loaderBarOuterEl);
	};
};

