sl.fieldDef.signature = {
	"init":function() {	
		var self = this;
		self.el = sl.dg("",self.contEl,"img",{"src":"","style":{"display":"none","height":"48px","cursor":"pointer"}});
		self.butEl = sl.dg("",self.contEl,"button",{"innerHTML":"en-us|Signature..."});
		
		function takeSig() {
			var overlay = new sl.viewOverlay({"view":self.view,"noCloseButton":true,"fullScreen":true});
			
			var reset = sl.dg("",overlay.elContent,"button",{"innerHTML":"en-us|RESET","style":{"marginRight":"10px"}});
			reset.addEventListener("click",function() {
				sigPoints = [];
				start = 0;
				ctx.clearRect(0,0,canvas.width,canvas.height);
			});
			
			var use = sl.dg("",overlay.elContent,"button",{"innerHTML":"en-us|USE","style":{"marginRight":"10px"}});
			use.addEventListener("click",function() {
				var xMax = 0, yMax = 0, xMin = 10000, yMin = 10000;
				for (var i = 0; i < sigPoints.length; i++) {
					var p = sigPoints[i];
					xMax = Math.max(xMax,p[0]);
					yMax = Math.max(yMax,p[1]);
					xMin = Math.min(xMin,p[0]);
					yMin = Math.min(yMin,p[1]);
				}
				var w = xMax - xMin, h = yMax - yMin, scale = 48 / h;
				var x, y, ox, oy;
				
				var canvas = document.createElement('canvas');
				canvas.setAttribute('width',Math.round(48*(w/h)));
				canvas.setAttribute('height',48);
			
				var ctx = canvas.getContext('2d');
				ctx.strokeStyle = "#000";
				ctx.lineWidth = 1;
				
				for (var i = 0; i < sigPoints.length; i++) {
					var p = sigPoints[i];
					x = (p[0] - xMin) * scale;
					y = (p[1] - yMin) * scale;
					
					if (p[2] != 0) {
						ctx.beginPath();
						ctx.moveTo(ox,oy);
						ctx.lineTo(x,y);
						ctx.stroke();
						ctx.closePath();
					}
					ox = x;
					oy = y;
				}
				self.applyValue(sl.jsonEncode(sigPoints)+";"+canvas.toDataURL());
				overlay.destruct();
			});
			
			var use = sl.dg("",overlay.elContent,"button",{"innerHTML":"en-us|Cancel"});
			use.addEventListener("click",function() {
				overlay.destruct();
			});
				
			sl.dg("",overlay.elContent,"div",{"className":"cb"});
			
			sl.dg("",overlay.elContent,"label",{"innerHTML":"en-us|Sign Below"});
			
			var w = self.view.width - 30, h = self.view.height - 120;
			var sigPoints = [], start = 0, now = 0, drawing = 0, lastDrawing = 0;
			var lastX = 0, lastY = 0;
			
			function mouse(e) {
				sl.mouseCoords(e);
				
				now = (new Date()).getTime();
				
				var type = 0;
				switch (e.convType) {
					case "mousedown":
						if (start == 0) start = now;
						type = 0;
						lastX = e.offsetX;
						lastY = e.offsetY;

						ctx.strokeStyle = "#000";
						ctx.lineWidth = 2;

						drawing |= 3;
						break;
					
					case "mouseover":
						drawing |= 2;
						break;
												
					case "mouseout":
						drawing &= 1;
						break;	
							
					case "mousemove":
						type = 1;
						break;
						
					case "mouseup":
						drawing &= 2;
						type = 2;
						break;
				}
				
				sl.cancelBubble(e);
				sl.preventDefault(e);
				
				if ((drawing & 3) == 3 && !((lastDrawing & 3) == 3)) ctx.beginPath();
				
				if ((drawing & 3) == 3) {
					sigPoints.push([e.offsetX,e.offsetY,type,now-start]);
					
					ctx.moveTo(lastX,lastY);
					ctx.lineTo(e.offsetX,e.offsetY);
				}
				
				if ((lastDrawing & 3) == 3 && !((drawing & 3) == 3)) {
					ctx.stroke();
					ctx.closePath();
				}
				
				lastDrawing = drawing;
				lastX = e.offsetX;
				lastY = e.offsetY;
			};
			
			var canvas = sl.dg("",overlay.elContent,"canvas",{"style":{"width":w+"px","height":h+"px"}});
			
			canvas.setAttribute('width',w);
			canvas.setAttribute('height',h);
			
			canvas.addEventListener("mousedown",mouse,true);
			canvas.addEventListener("mousemove",mouse,true);
			canvas.addEventListener("mouseout",mouse,true);
			canvas.addEventListener("mouseover",mouse,true);
			canvas.addEventListener("mouseup",mouse,true);
			
			if (sl.config.isMobile) {
				canvas.addEventListener("touchstart",mouse,true); 
				canvas.addEventListener("touchmove",mouse,true);
				canvas.addEventListener("touchend",mouse,true);
			}
			
			var ctx = canvas.getContext('2d');
			overlay.updateContentSize();
		};
		
		self.el.addEventListener("click",takeSig);
		self.butEl.addEventListener("click",takeSig);
		
		return true;
	},
	"setValue":function(value) {
		if (value && this.el) {
			var d = value.split(";");
			var coords = d.shift();
			var img = d.join(";");
			this.el.src = img;
			this.el.style.display = "";
			this.butEl.style.display = "none";
		}				
		return true;
	}
};
