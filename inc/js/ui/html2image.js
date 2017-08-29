/* CartLemmy's HTML2IMAGE
 * 
 * (c) 2012 Josh "CartLemmy" Merritt
 * cartlemmy@gmail.com
 * 
 */


function html2Image(el,callback,o) {
	var self = this;
	
	self.DEBUG = 0;
	self.el = el;
	self.callback = callback;
	self.imagesLeftToLoad = 0;
	self.imagesToLoad = [];
	self.images = {};
	
	self.setOptions = function(o) {
		for (i in o) {
			var n = "set" + i.charAt(0).toUpperCase() + i.substr(1);
			if (self[n]) { // Has a setter
				self[n](o[i]);
			} else {
				self[i] = o[i];
			}
		}
	};
	
	self.loadImages = function(el) {
		var i, top = false;
		if (!el) {
			el = self.el;
			top = true
		}
		if (el.nodeType == 1) {
			var style = window.getComputedStyle(el,null);
			var im = self.getBgImages(style);
			if (im) {
				for (i = 0; i < im.length; i++) {
					if (self.imagesToLoad.indexOf(im[i].src) == -1) {
						self.imagesToLoad.push(im[i].src);
					}					
				}
			}
			if (el.nodeName == "IMG") {
				if (self.imagesToLoad.indexOf(el.src) == -1) {
					self.imagesToLoad.push(el.src);
				}
			}
			for (i = 0; i < el.childNodes.length; i++) {
				if (self.processElement(el.childNodes[i])) self.loadImages(el.childNodes[i]);
			}
		}
		
		if (top) {
			if (self.imagesToLoad.length == 0) {
				var im = self.parseDom(self.el);
				self.callback(self.resize(im));
			} else {
				for (i = 0; i < self.imagesToLoad.length; i++) {
					if (self.imagesToLoad[i]) {
						self.imagesLeftToLoad++;
						var im = new Image();
						im.src = self.imagesToLoad[i];
						im.onerror = function() {
							self.images[this.src] = false;
							self.imagesLeftToLoad--;
							console.log("self.imagesLeftToLoad: "+self.imagesLeftToLoad);
							if (self.imagesLeftToLoad == 0) {							
								var im = self.parseDom(self.el);
								self.callback(self.resize(im));
							}
						};
						im.onload = function() {
							self.images[this.src] = this;
							self.imagesLeftToLoad--;
							if (self.imagesLeftToLoad == 0) {
								var im = self.parseDom(self.el);
								self.callback(self.resize(im));
							}
						};
					}
				}
			}
		}
	};
	
	self.processElement = function(el) {
		if (self.stopAt) {
			for (var i in self.stopAt) {
				if (i == "el") {
					if (self.stopAt[i] == el) return false;
				} else if (self.stopAt[i] == el[i]) return false;
			}
		}
		return true;
	};
	
	self.resize = function(im) {
		var sx = 0, sy = 0, sw = im.canvas.width, sh = im.canvas.height;
		
		if (self.autoCrop) {
			sx = Math.max(0,self.bounds.x1 - self.margin);
			sy = Math.max(0,self.bounds.y1 - self.margin);
			sw = Math.min(im.canvas.width,self.bounds.x2 - self.bounds.x1 + self.margin * 2);
			sh = Math.min(im.canvas.height,self.bounds.y2 - self.bounds.y1 + self.margin * 2);
			
		}

		resizedCanvas = document.createElement('canvas');
		resizedCanvas.setAttribute('width',self.width);
		resizedCanvas.setAttribute('height',self.height);
		resizedCtx = resizedCanvas.getContext('2d');
		
		var sourceRatio = sw / sh, destRatio = self.width / self.height;
		
		if (sourceRatio > destRatio) {
			sw = sh * destRatio;
		} else {
			sh = sw / destRatio;
		}
		
		resizedCtx.drawImage(
			im.canvas,
			sx,sy,sw,sh,
			0,0,self.width,self.height
		);
		
		if (self.fadeBottom && sourceRatio < destRatio) {
			var h = Math.round(self.height/4);
			var grad = resizedCtx.createLinearGradient(0,self.height-h,0,self.height);
			grad.addColorStop(0,"rgba("+self.bgColor[0]+","+self.bgColor[1]+","+self.bgColor[2]+",0.0)");
			grad.addColorStop(1,"rgba("+self.bgColor[0]+","+self.bgColor[1]+","+self.bgColor[2]+",1.0)");
			resizedCtx.fillStyle = grad;
			resizedCtx.fillRect(0,self.height-h,self.width,h);
		}
		
		return resizedCanvas;
	};
	
	self.parseDom = function(el,parent) {
		if (el.style && el.style.display == "none" && el.nodeType != 3) return;
		var node = {};
		
		if (!parent) self.nodes = [];
		
		node.nodeNum = self.nodes.length;
	
		node.el = el;
		
		node.tree = [];
		if (parent) {
			for (var i = 0; i < parent.tree.length; i++) {
				node.tree.push(parent.tree[i]);
			}
			node.tree.push(node.nodeNum);
		} else {
			self.bounds.x1 = self.bounds.y1 = 100000;
		}
			
		if (el.nodeType == 1) {
			node.bounds = el.getBoundingClientRect();
			
			var o = parent ? self.offsetFrom(parent.el,el) : {"x":0,"y":0};
			
			var style = window.getComputedStyle(el,null);
			
			var padding = self.metrics(style,'padding');	
			var border = self.metrics(style,'border','width');
			var margin = self.metrics(style,'margin');
				
			node.inner = {
				"x":padding.left + border.left,
				"y":padding.top + border.top,
				"w":el.offsetWidth-(border.left+border.right+padding.left+padding.right),
				"h":el.offsetHeight-(border.top+border.bottom+padding.top+padding.bottom)
			};	
			
			
			//calculate metrics			
			node.padding = {"x":node.inner.x-padding.left,"y":node.inner.y-padding.top,"w":node.inner.w+(padding.left+padding.right),"h":node.inner.h+(padding.top+padding.bottom)};
			
			node.border = {"x":node.padding.x-border.left,"y":node.padding.y-border.top,"w":node.padding.w+(border.left+border.right),"h":node.padding.h+(border.top+border.bottom)};
			
			node.outer = {"x":node.border.x-margin.left,"y":node.border.y-margin.top,"w":node.border.w+(margin.left+margin.right),"h":node.border.h+(margin.top+margin.bottom)};
			
			node.pos = parent ? {"x":parent.pos.x+o.x,"y":parent.pos.y+o.y} : {"x":o.x,"y":o.y};
			
			var t = ["padding","border","inner","outer"];
			for (var i = 0; i < t.length; i++) {
				node[t[i]].x -= node.outer.x;
				node[t[i]].y -= node.outer.y;
			}
		
			node.canvas = document.createElement('canvas');
			node.canvas.setAttribute('width',Math.max(node.outer.w,el.nodeName == "BODY" ? self.docSize().width : 0));
			node.canvas.setAttribute('height',Math.max(node.outer.h,el.nodeName == "BODY" ? self.docSize().height : 0));
			node.ctx = node.canvas.getContext('2d');
					
			//TODO: implement style.getPropertyValue("background-clip")
			var radius = self.borderRadius(style);
						
			// Set clipping:
			node.ctx.save();
			node.ctx.beginPath();
			
			node.ctx.moveTo(node.border.x + radius.tl[0],node.border.y);
			node.ctx.lineTo(node.border.x + node.border.w - radius.tr[0],node.border.y);
			node.ctx.bezierCurveTo(node.border.x + node.border.w - radius.tr[0] / 2, node.border.y, node.border.x + node.border.w, node.border.y + radius.tr[1] / 2, node.border.x + node.border.w, node.border.y + radius.tr[1]);
			node.ctx.lineTo(node.border.x + node.border.w, node.border.y + node.border.h - radius.br[1]);
			node.ctx.bezierCurveTo(node.border.x + node.border.w, node.border.y + node.border.h - radius.br[1] / 2, node.border.x + node.border.w - radius.br[0] / 2, node.border.y + node.border.h, node.border.x + node.border.w - radius.br[0], node.border.y + node.border.h);
			node.ctx.lineTo(node.border.x + radius.bl[0], node.border.y + node.border.h);
			node.ctx.bezierCurveTo(node.border.x + radius.bl[0] / 2, node.border.y + node.border.h, node.border.x, node.border.y + node.border.h - radius.bl[1] / 2, node.border.x, node.border.y + node.border.h - radius.bl[1]);
			node.ctx.lineTo(node.border.x, node.border.y + radius.tl[1]);
			node.ctx.bezierCurveTo(node.border.x, node.border.y + radius.tl[1] / 2, node.border.x + radius.tl[0] / 2, node.border.y, node.border.x + radius.tl[0],node.border.y);
					
			node.ctx.clip();
			
			//render background color
			var bgColor = self.toRGBA(self.gp(style,"background-color"));
			if (bgColor[3] != 0) {
				node.ctx.fillStyle = self.toColorStyle(bgColor);  
				node.ctx.fillRect(node.border.x, node.border.y, node.border.w,node.border.h);	
			}
			
			//render border:
			if (border.top || border.bottom || border.left || border.right) {
				var borderColor = self.styleLRTB(style,"border","color");
				var borderStyle = self.styleLRTB(style,"border","style");
				
				
				node.ctx.beginPath();
				node.ctx.lineCap = "butt";
				node.ctx.lineWidth = 1;
				
				var borderPos = 0;
				
				//Top border line
				var w = border.top, y = node.border.y;
				node.ctx.strokeStyle = borderColor.top;
				node.ctx.beginPath();
				for (var x = node.border.x + radius.tl[0]; x <= node.border.x + node.border.w - radius.tr[0]; x++) {
					self.segment(node.ctx,borderPos,x,y,w,1,borderStyle.top);
					borderPos += 1 / w;
				}
				node.ctx.closePath();
				
				//Top right corner
				borderPos = self.drawArc(node.ctx,borderPos,"tr",node.border.x + node.border.w,node.border.y,radius,border,borderColor,borderStyle);
				
				//Right border line		
				var w = border.right, x = node.border.x + node.border.w;
				node.ctx.strokeStyle = borderColor.right;
				node.ctx.beginPath();
				for (var y = node.border.y + radius.tr[1]; y <= node.border.y + node.border.h - radius.br[1]; y++) {
					self.segment(node.ctx,borderPos,x,y,w,2,borderStyle.right);
					borderPos += 1 / w;
				}
				node.ctx.closePath();
				
				//Bottom right corner
				borderPos = self.drawArc(node.ctx,borderPos,"br",node.border.x + node.border.w,node.border.y + node.border.h,radius,border,borderColor,borderStyle);
				
				//Bottom border line
				var w = border.bottom, y = node.border.y + node.border.h;
				node.ctx.strokeStyle = borderColor.bottom;
				node.ctx.beginPath();
				for (var x = node.border.x + node.border.w - radius.br[0]; x >= node.border.x + radius.bl[0]; x--) {
					self.segment(node.ctx,borderPos,x,y,w,3,borderStyle.bottom);
					borderPos += 1 / w;
				}
				node.ctx.closePath();
				
				//Bottom left corner
				borderPos = self.drawArc(node.ctx,borderPos,"bl",node.border.x,node.border.y + node.border.h,radius,border,borderColor,borderStyle);
				
				//Left border line		
				var w = border.left, x = node.border.x;
				node.ctx.strokeStyle = borderColor.left;
				node.ctx.beginPath();
				for (var y = node.border.y + node.border.h - radius.bl[1]; y >= node.border.y + radius.tl[1]; y--) {
					self.segment(node.ctx,borderPos,x,y,w,0,borderStyle.left);
					borderPos += 1 / w;
				}
				node.ctx.closePath();
				
				//Bottom right corner
				borderPos = self.drawArc(node.ctx,borderPos,"tl",node.border.x,node.border.y,radius,border,borderColor,borderStyle);
			}
			
			//render background image(s)
			var im = self.getBgImages(style,node.border.w,node.border.h);

			if (im) {
				//TODO: implement style.getPropertyValue("background-origin")
				//TODO: implement style.getPropertyValue("background-position")
				for (var i = 0; i < im.length; i++) {
					var bgIm = im[i];
					bgIm.img = self.images[bgIm.src];
					
					if (bgIm.img) {
						var orX = bgIm.position.x, orY = bgIm.position.y;
						if (bgIm.repeat.x) {
							while (orX > 0) { orX -= bgIm.img.width; }					
						}
						if (bgIm.repeat.y) {
							while (orY > 0) { orY -= bgIm.img.height; }					
						}

						var tot = 0;
						for (var x = orX; x <= (bgIm.repeat.x ? node.border.w : orX); x += bgIm.img.width) {
							for (var y = orY; y <= (bgIm.repeat.y ? node.border.h : orY); y += bgIm.img.height) {
								if (bgIm.img) {
									node.ctx.drawImage(bgIm.img,x+node.border.x,y+node.border.y);
								}
							}
						}
					}
				}
			}
			
			node.ctx.restore();
			
			if (el.nodeName == "IMG") {
				if (self.images[el.src]) node.ctx.drawImage(self.images[el.src],node.inner.x,node.inner.y);
			} else if (el.nodeName == "CANVAS") {
				node.ctx.drawImage(el,node.inner.x,node.inner.y);
			}

			//render child nodes
			for (var i = 0; i < el.childNodes.length; i++) {
				self.parseDom(el.childNodes[i],node);
			}		
		} else if (el.nodeType == 3) {
			
			var range = self.document.createRange();
			range.setStart(el,0);
			range.setEnd(el,el.textContent.length);
						
			var rect = range.getBoundingClientRect();
			
			if (rect.width && rect.height) {
				node.pos = {"x":Math.floor(rect.left),"y":Math.floor(rect.top)};
				node.outer = {"x":0,"y":0,"w":rect.width,"h":rect.height};
				node.margin = node.border = node.inner = node.padding = node.outer;
				
				node.canvas = document.createElement('canvas');
				node.canvas.setAttribute('width',node.outer.w);
				node.canvas.setAttribute('height',node.outer.h);
				node.ctx = node.canvas.getContext('2d');
				
				var cont = range.startContainer.parentNode;
				var style = window.getComputedStyle(cont,null);
				
				var font = [];
				var a = ["font-style","font-variant","font-weight","font-size","font-family"];
		
				for (var i = 0; i < a.length; i ++) {
					font.push(self.gp(style,a[i]));
				}
				node.ctx.font = font.join(" ");
				node.ctx.textBaseline = "top";
				node.ctx.fillStyle = self.gp(style,"color");
				
				for (var i = 0; i < el.textContent.length; i++) {
					range.setStart(el,i);
					range.setEnd(el,i+1);
					
					var rect = range.getBoundingClientRect();
					
					node.ctx.fillText(el.textContent.charAt(i),rect.left - node.pos.x,rect.top - node.pos.y);
					if (self.DEBUG) {
						node.ctx.lineWidth = 0.5;
						node.ctx.strokeStyle = "rgba(255,0,255,1)";
						node.ctx.strokeRect(rect.left - node.pos.x,rect.top - node.pos.y,rect.width,rect.height);
					}
				}
			}
			
		}
		
		// DEBUG: show metrics:
		if (self.DEBUG && el.nodeName != "BODY" && node.inner) {
			node.ctx.lineWidth = 1;
			//inner:
			node.ctx.strokeStyle = "rgba(255,0,0,1)";
			node.ctx.strokeRect(node.inner.x+0.5,node.inner.y+0.5,node.inner.w-1,node.inner.h-1);
			//padding:
			node.ctx.strokeStyle = "rgba(192,128,0,1)";
			node.ctx.strokeRect(node.padding.x+0.5,node.padding.y+0.5,node.padding.w-1,node.padding.h-1);
			//border:
			node.ctx.strokeStyle = "rgba(0,192,0,1)";
			node.ctx.strokeRect(node.border.x+0.5,node.border.y+0.5,node.border.w-1,node.border.h-1);
			//margin:
			node.ctx.strokeStyle = "rgba(0,0,255,1)";
			
			node.ctx.strokeRect(node.outer.x+0.5,node.outer.y+0.5,node.outer.w-1,node.outer.h-1);
		}
		
		if (!parent) {
			self.nodes.sort(function(a,b){
				if (a.el.style && a.el.style.zIndex && b.el.style && b.el.style.zIndex && a.el.style.zIndex != b.el.style.zIndex) return a.el.style.zIndex - b.el.style.zIndex;

				if (a.tree.length != b.tree.length) return a.tree.length - b.tree.length;
				
				for (var i = 0; i < Math.max(a.tree.length,b.tree.length); i++) {
					var at = a.tree[i] ? a.tree[i] : 0;
					var bt = b.tree[i] ? b.tree[i] : 0;
					if (at != bt) {
						return at - bt;
					}
				}
				
				return 0;
			});
			
			for (var i = 0; i < self.nodes.length; i++) {
				var n = self.nodes[i];
				if (n.canvas && n.canvas.width && n.canvas.height) {
					try {
						node.ctx.drawImage(n.canvas,n.pos.x-n.border.x,n.pos.y-n.border.y);
						self.bounds.x1 = Math.min(n.pos.x - n.border.x,self.bounds.x1);
						self.bounds.y1 = Math.min(n.pos.y - n.border.y,self.bounds.y1);
						self.bounds.x2 = Math.max(n.pos.x - n.border.x + n.outer.w,self.bounds.x2);
						self.bounds.y2 = Math.max(n.pos.y - n.border.y + n.outer.h,self.bounds.y2);
					} catch (e) {
						console.log(e,n.canvas,n.pos.x-n.border.x,n.pos.y-n.border.y);
					}
				}
			}
		}
		
		if (node && node.el) {
			self.nodes.push(node);
		}
		
		return node;
	};
	
	self.toRGBA = function(txt) {
		if (txt.substr(0,4) == "rgba") {
			rgba = txt.substr(txt.indexOf("(")+1,txt.indexOf(")") - txt.indexOf("(") - 1).split(",");
		} else if (txt.substr(0,3) == "rgb") {
			rgba = txt.substr(txt.indexOf("(")+1,txt.indexOf(")") - txt.indexOf("(") - 1).split(",");
			rgba[3] = 1;
		}
		for (var i = 0; i < 4; i++) {
			if (typeof(rgba[i]) == "string") {
				rgba[i] = Number(rgba[i].replace(/^\s+|\s+$/g,''));
			}
		}
		return rgba;
	};
	
	self.toColorStyle = function(rgba) {
		if (rgba[3] == 1) return "rgb("+rgba[0]+","+rgba[1]+","+rgba[2]+")";
		return "rgba("+rgba[0]+","+rgba[1]+","+rgba[2]+","+rgba[3]+")";
	}
	
	self.metrics = function(style,t,suff) {
		return {
			"left":self.px(self.gp(style,t+"-left"+(suff?"-"+suff:""))),
			"right":self.px(self.gp(style,t+"-right"+(suff?"-"+suff:""))),
			"top":self.px(self.gp(style,t+"-top"+(suff?"-"+suff:""))),
			"bottom":self.px(self.gp(style,t+"-bottom"+(suff?"-"+suff:"")))
		};
	};
	
	self.styleLRTB = function(style,t,suff) {
		return {
			"left":self.gp(style,t+"-left"+(suff?"-"+suff:"")),
			"right":self.gp(style,t+"-right"+(suff?"-"+suff:"")),
			"top":self.gp(style,t+"-top"+(suff?"-"+suff:"")),
			"bottom":self.gp(style,t+"-bottom"+(suff?"-"+suff:""))
		};
	};
	
	
	
	self.borderRadius = function(style) {
		return {
			"tl":self.sp(self.gp(style,"border-top-left-radius"),2),
			"tr":self.sp(self.gp(style,"border-top-right-radius"),2),
			"bl":self.sp(self.gp(style,"border-bottom-left-radius"),2),
			"br":self.sp(self.gp(style,"border-bottom-right-radius"),2)
		};
	};
	
	
	self.px = function(v,o) {
		if (v == null) return 0;
		var t = v.replace(/[\d\.\-]+/,'');
		switch (t) {
			case "left":
			case "right":
			case "top":
			case "bottom":
			case "center":
				return t;
				
			case "%":
				v = o * (Number(v.replace(/[^\d\.\-]+/,'')) / 100);
				break;
			
			default: case "px":
				v = Number(v.replace(/[^\d\.\-]+/,''));
				break;
			
		}
		return isNaN(v) ? 0 : v;
	};
	
	self.getBgImages = function(style,w,h) {
		var bgImage = self.gp(style,"background-image");
		if (bgImage && bgImage != "none" && bgImage.charAt(0) != "-") {
			bgImage = bgImage.split(",");
			var bgRepeat = self.gp(style,"background-repeat");
			if (bgRepeat) bgRepeat = bgRepeat.split(","); 
			if (!bgRepeat) bgRepeat = new Array(bgImage.length);
			var bgSize = self.gp(style,"background-size");
			if (bgSize) bgSize = bgSize.split(",");
			var bgPosition = self.gp(style,"background-position");
			if (bgPosition) bgPosition = bgPosition.split(","); 
			if (!bgPosition) bgPosition = new Array(bgImage.length);
					
			var rv = [];
			for (var i = 0; i < bgImage.length; i++) {
				if (bgPosition[i]) {
					var t = bgPosition[i].replace(/^\s+|\s+$/g,'').split(" ");
					bgPosition[i] = {
						"x":self.px(t[0],w),
						"y":self.px(t[0],h)
					};
				} else {
					bgPosition[i] = {"x":0,"y":0};
				}
	
				var v = {"x":0,"y":0};
				switch (bgRepeat[i] ? bgRepeat[i] : "repeat") {
					case "repeat": v.x = 1; v.y = 1; break;
					case "repeat-x": v.x = 1; break;
					case "repeat-y": v.y = 1; break;
				}
				bgRepeat[i] = v;
				
				rv.push({
					"src":bgImage[i].split(/(\(\'?|\'?\))/)[2],
					"position":bgPosition[i],
					"repeat":bgRepeat[i],
					"size":typeof(bgSize) == "object"  && bgSize != null && bgSize[i] ? bgSize[i].replace(/^\s+|\s+$/g,'') : null
				});
			}
			return rv;
		}
		return false;
	};
	
	self.gp = function(s,p) {
		return s.getPropertyValue ? s.getPropertyValue(p) : null;
	}
	
	self.sp = function(d,n) {
		d = d.split(" ");
		var rv = [];
		for (var i = 0; i < n; i++) {
			rv.push(self.px(i < d.length ? d[i] : d[0]));
		}
		return rv;
	};
	
	self.offsetFrom = function(e1,e2) {
		e1 = self.getPos(e1);
		e2 = self.getPos(e2);
		return {"x":(e2.x-e1.x),"y":(e2.y-e1.y)};
	};
	
	self.getPos = function(el) {
		var curleft = curtop = 0;
		if (el.offsetParent) {
			do {
				curleft += el.offsetLeft;
				curtop += el.offsetTop;
			} while (el = el.offsetParent);
		}
		return {"x":curleft,"y":curtop};
	};
	
	self.docSize = function() {
		if (window.innerHeight)
			return {"width":window.innerWidth,"height":window.innerHeight};
		else if (document.documentElement && document.documentElement.clientHeight)
			return {"width":document.documentElement.clientWidth,"height":document.documentElement.clientHeight};
		else if (document.body)
			return {"width":window.innerWidth,"height":window.innerHeight};
	};

	self.drawArc = function(ctx,pos,corner,x,y,radius,width,color,style) {
		var corners = ["tl","tr","br","bl"], sides = ["top","right","bottom","left"];
		var cornerInfo = {
			"tl":{"a":2,"s":3},
			"tr":{"a":3,"s":0},
			"br":{"a":0,"s":1},
			"bl":{"a":1,"s":2}
		};
		
		
		var ci = cornerInfo[corner];
		var startAngle = ci.a * Math.PI / 2, midAngle = (ci.a + 0.5) * Math.PI / 2;
		var xm = Math.cos(midAngle) < 0 ? 1 : -1;
		var ym = Math.sin(midAngle) < 0 ? 1 : -1;
		
		var startSide = sides[ci.s], endSide = sides[(ci.s + 1) & 3];
		
		//Find arc center:
		x += radius[corner][0] * xm;
		y += radius[corner][1] * ym;
		
		ctx.save();
		ctx.lineWidth = 1.5;
		var caution = 1;
		for (var angle = startAngle; angle < startAngle + Math.PI / 2; angle += aStep) {
			var p = (angle - startAngle) / (Math.PI / 2);
			
			var w = width[startSide] * (1 - p) + width[endSide] * p;
			
			var xd = Math.cos(angle) * radius[corner][0], yd = Math.sin(angle) * radius[corner][1];
						
			var aStep = (1 / (Math.sqrt(xd*xd+yd*yd) * Math.PI / 2)) * caution;
			
			ctx.strokeStyle = color[p < 0.5 ? startSide : endSide];
			ctx.beginPath();
			
			self.segment(ctx,pos,x + xd,y + yd,w,(angle - Math.PI) / (Math.PI / 2),style[p < 0.5 ? startSide : endSide]);
			
			ctx.closePath();
									
			pos += (1 / w) * caution;
		}
		ctx.restore();
		return pos;
	};
	
	self.segment = function(ctx,pos,x,y,lineWidth,angle,type) {
		
		lineWidth = Math.max(1,lineWidth);
		angle *= Math.PI / 2;
		switch (type) {
			case "dotted":
				if (pos % 2 >= 1) return;
				break;
			
			case "dashed":
				if (pos % 6 >= 3) return;
				break;	
		}

		ctx.moveTo(self.snapx(angle,x),self.snapy(angle,y));
		if (type == "double") {
			ctx.lineTo(self.snapx(angle,x+Math.cos(angle)*lineWidth*(1/3)),self.snapy(angle,y+Math.sin(angle)*lineWidth*(1/3)));
			ctx.moveTo(self.snapx(angle,x+Math.cos(angle)*lineWidth*(2/3)),self.snapy(angle,y+Math.sin(angle)*lineWidth*(2/3)));
		}
		ctx.lineTo(self.snapx(angle,x+Math.cos(angle)*lineWidth),self.snapy(angle,y+Math.sin(angle)*lineWidth));
		ctx.stroke();
	};
	
	self.snapx = function(angle,p) {
		if (Math.abs(Math.sin(angle)) > 0.01) return Math.floor(p) + 0.5;
		return p;
	};
	
	self.snapy = function(angle,p) {
		if (Math.abs(Math.cos(angle)) > 0.01) return Math.floor(p) + 0.5;
		return p;
	};
	
	self.setOptions({
		"width":0,
		"height":0,
		"margin":10,
		"fadeBottom":true,
		"bgColor":[255,255,255],
		"bounds":{"x1":0,"y1":0,"x2":0,"y2":0},
		"document":document
	});
	
	if (o) self.setOptions(o);
	
	if (self.width == 0) self.width = el.offsetWidth;
	if (self.height == 0) self.height = el.offsetHeight;
	
	self.loadImages();
}
