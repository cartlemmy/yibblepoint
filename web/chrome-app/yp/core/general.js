//Compatibility:
if (!File.prototype.slice) {
  File.prototype.slice = function() {
    "use strict";
    
    if (File.prototype.mozSlice) {
			return this.mozSlice.apply(this,arguments);
		}
		if (File.prototype.webkitSlice) {
			return this.webkitSlice.apply(this,arguments);
		}
		throw("File slice not supported");
	}
};

//Other
sl.parseHTML = function(html) {
	return html.replace(/\[icon\:([\w\d\-]+)\]/gi,'<i class="fa fa-$1"></i>');
};

sl.dg = function(id,p,t,a,pre) {
	if (arguments.length == 1) return document.getElementById(id);
	
	function dg() {
		if (id !== "" && document.getElementById(id)) {
			return document.getElementById(id);
		} else {
			if (t == "text") {
				var d = document.createTextNode(a);
			} else {
				var d = document.createElement(t);
			}
			
			if (id !== "") { d.id = id; }
			if (pre && pre.before && !p) p = pre.before.parentNode;
			if (pre && pre.after && !p) p = pre.after.parentNode;
			
			if (p) {
				if (pre && pre.before) {
					p.insertBefore(d, pre.before);
				} else if (pre && pre.after) {
					if (pre.after.nextSibling) {
						p.insertBefore(d, pre.after.nextSibling);
					} else {
						p.appendChild(d);
					}
				} else if (pre) {
					p.insertBefore(d, p.firstChild);
				} else {
					p.appendChild(d);
				}
			}
			if (t != "text") {
				if (typeof(a) == "string") {
					d.innerHTML = sl.parseHTML(a);
				} else {
					if (a && a.innerHTML) a.innerHTML = sl.parseHTML(a.innerHTML);
					sl.recursiveSet(d,a);
				}
			}
			return d;
		}
		return false;
	};
	
	var special = ["matchParent","fillRemaining"], specialData = {}, timer, element;
	
	function update() {
		if (!element.parentNode) {
			clearInterval(timer);
			return;
		}
		var pad = sl.getTotalElementSize(element.parentNode,1);
		var inner = sl.innerSize(element.parentNode);
		for (var n in specialData) {
			var v = specialData[n];
			if (v) {
				switch (n) {
					case "matchParent":
						for (var i = 0; i < v.length; i++) {
							switch (v[i]) {
								case "width":
									element.style.width = inner.width+"px";
									break;
									
								case "height":
									element.style.height = inner.height+"px";
									break;
							}
						}
						break;
						
					case "fillRemaining":
						var c = element.parentNode.childNodes;
						switch (v) {
							case "width":
								var cPad = 0;
								for (var j = 0; j < c.length; j++) {
									if (c[j] != element) cPad += sl.getTotalElementSize(c[j]).width;
								}
								element.style.width = (inner.width-cPad)+"px";
								break;
								
							case "height":
								var cPad = 0;
								for (var j = 0; j < c.length; j++) {
									if (c[j] != element) cPad += sl.getTotalElementSize(c[j]).height;
								}
								element.style.height = (inner.height-cPad)+"px";
								break;
						}
						break;
				}
			}
		}
	};
	
	if (a) {
		var useUpdater = false;
		for (var i = 0; i < special.length; i++) {
			specialData[special[i]] = a[special[i]];
			if (specialData[special[i]]) {
				switch (special[i]) {
					default:
						useUpdater = true;
						break;
				}
				delete a[special[i]];
			}
		}
		if (useUpdater) timer = setInterval(update,100);
	}
	
	element = dg();

	return element;
};

sl.cb = function(parent) {
	return sl.dg("",parent,"div",{"style":{"clear":"both"}});
};

sl.tableHead = function(table,cols) {
	var thead = sl.dg("",table,"thead");
	var tr = sl.dg("",table,"tr");
	for (var i = 0; i < cols.length; i++) {
		sl.dg("",tr,"th",{"innerHTML":cols[i]});
	}
	return thead;
};

sl.tableRow = function (tbody,cols) {
	var tr = sl.dg("",tbody,"tr");
	for (var i = 0; i < cols.length; i++) {
		sl.dg("",tr,"td",{"innerHTML":cols[i]});
	}
	return tr;	
};

sl.format = function() {
	var args = [], i;
	for (i = 0; i < arguments.length; i++) {
		args.push(arguments[i]);
	}
	var txt = args.shift().split(/(%[\d\w\-\_]*%)/);

	var replace = null;
	if (typeof(args[0]) == "object") {
		replace = args.shift();
	}
	
	for (i = 1; i < txt.length; i += 2) {
		var n = (i - 1) >> 1;
		if (txt[i] != "%%") {
			var a = txt[i].substr(1,txt[i].length - 2);
			if (replace && replace[a] !== undefined) {
				txt[i] = replace[a];
				continue;
			} else {
				n = Number(txt[i].replace(/[^\d]+/gi,"")) - 1;
			}
		}
		txt[i] = args[n];
	}
	return txt.join("");
};

sl.formatValue = function(type,value,opts) {
	if (!opts) opts = {};
	opts.type = type;
	opts.value = value;
	var slV = new sl.value(opts);
	return slV.toString();
};

sl.recursiveSet = function(d,a) {
	for (var i in a) {
		if (typeof(a[i]) == "object") {
			sl.recursiveSet(d[i],a[i]);
		} else {
			if (typeof(d) == "undefined") d = {};
			d[i] = a[i];
		}
	}
};

sl.isChildOf = function(el,parent) {
	if (el == parent) return false;
	while (el.parentNode != parent) {
		if (el.parentNode) {
			el = el.parentNode;
		} else return false;
	}
	return true;
};

sl.cancelBubble = function(e) {
	if (e.originEvent) e = e.originEvent;
	var evt = e ? e : window.event;
	if (evt.stopPropagation) evt.stopPropagation();
	if (evt.cancelBubble!=null) evt.cancelBubble = true;
};

sl.preventDefault = function(e) {
	if (e.originEvent) e = e.originEvent;

  if ( e.preventDefault ) {
		e.preventDefault();
	} else {
		e.returnValue = false;
	}
};

sl.touchEvents = {"touchstart":"mousedown","touchmove":"mousemove","touchend":"mouseup"};

sl.mouseCoords = function(e) {
	if (!e) return;
	e.convType = e.type;
	if (sl.touchEvents[e.type]) {
		e.convType = sl.touchEvents[e.type];
		var touch = event.touches[0];
		if (touch) {
			var t = ["clientX","clientY","screenX","screenY","pageX","pageY","target"];
			for (var i = 0; i < t.length; i++) {
				e[t[i]] = touch[t[i]];
			}
		}
	}
	if (e.offsetX === undefined) {
		var el = e.target,	x = y = 0;

		while (el && !isNaN(el.offsetLeft) && !isNaN(el.offsetTop)) {
			x += el.offsetLeft - el.scrollLeft;
			y += el.offsetTop - el.scrollTop;
			el = el.offsetParent;
		}

		e.offsetX = e.clientX - x;
		e.offsetY = e.clientY - y;
	}
};

sl.unixTS = function(dontRound) {
	var currentTime = new Date();
	if (dontRound) {
		return currentTime.getTime() / 1000;
	} else {
		return Math.floor(currentTime.getTime() / 1000);
	}
};

sl.getKeyFromEvent = function(e) {
	var k = window.event ? (event ? event.keyCode : e.keyCode) : e.which;

	if (k < 0x30) {
		var a = [0,1,2,3,4,5,6,7,'backspace','tab',10,11,12,'enter',14,15,'shift',
		'ctrl','alt','pause','caps',21,22,23,24,25,26,'escape',28,29,30,31,'space',
		'pgup','pgdown','end','home','left','up','right','down',41,42,43,44,'insert','delete',47];
		return a[k];
	} else {
		switch (k) {
			case 109: return "minus";
			case 61: return "plus";
			case 144: return "numlock";
			case 188: return "lt";
			case 190: return "gt";
			default: return String.fromCharCode(k).toLowerCase();		
		}
	}
};
	
sl.typeOf = function(v) {
	var s = typeof(v);
	if (s === 'object') {
		if (v) {
			if (v instanceof Array) return 'array';
			if (v.tagName) return 'element';
			return 'object'
		}
		return 'null';
	}
	return s;
};
	
sl.jsonEncode = function(v,o) {
	if (!o) var o = {};
	var self = {};
	
	self.maxRecur = o.maxRecur ? o.maxRecur : 10;
	self.readable = !!o.readable;
	self.l = 0;

	
	self.to = function(v) {
		var s = typeof(v);
		if (s === 'object') {
			if (v) {
				if (v instanceof Array) return 'array';
				if (v.tagName) return 'element';
				return 'object'
			}
			return 'null';
		}
		return s;
	};
	
	self.pass = function(n,v,h) {
		return o.pass ? o.pass(n,v,h) : true;
	};
	
	self.eol = function(o,s) {
		if (!this.readable) return "";
		return (o.ne!==true || s != undefined ?"\n"+Array(Math.max(0,o.l+(s != undefined ? s : 0))).join("  "):"");
	};
	
	self.json = function(v,l,hin,ne) {
		var ne = {ne:ne,l:(l?l:0)};
		if (l > self.maxRecur) return "null"+self.eol(ne);
		
		var h = [];
		if (typeof(v) == "object") {
			for (var i in hin) { //Prevent circular references
				if (v == hin[i]) return "null"+self.eol(ne);
				h.push(hin[i]);
			}
		}
		h.push(v);
		
		//try {
			switch (self.to(v)) {
				case "number":
					return (isFinite(v) ? v : "null")+self.eol(ne);
					
				case "string":
					return v.safeString()+self.eol(ne);
					
				case "array":
					if (v.length == 0) return "[]"+self.eol(ne);
					return "["+self.eol(ne,2)+(function(v,d){var rv=[];for (var i = 0; i < v.length; i++){if (self.pass(i,v[i],h)){rv.push(d.json(v[i],l+1,h,true))}} return rv;})(v,self).join(","+self.eol(ne,2))+self.eol(ne,1)+"]"+self.eol(ne);
					
				case "boolean":
					return (v ? "true" : "false")+self.eol(ne);
				
				case "element":
					if (v.nodeName != "CANVAS" && v.nodeName != "IMG") return "null"+self.eol(ne);
						
				case "object":
					var	enc;
					if (enc = sl.extendedObjects.encode(v)) {
						return '"'+enc+'"'+self.eol(ne);
					}
					if (v.serialize) var v = v.serialize();
					return "{"+self.eol(ne,2)+(function(v,d){var rv=[];for (var i in v){if (self.pass(i,v[i],h)){rv.push(d.json(i,0,0,true)+":"+d.json(v[i],l+1,h,true))}} return rv;})(v,self).join(","+self.eol(ne,2))+self.eol(ne,1)+"}"+self.eol(ne);
					
				default:
					return "null"+self.eol(ne);
			}
		//} catch (e) {
		//	return "null"+self.eol(ne);
		//}
	};
	return self.json(v,0,[]);
};

sl.dataURIHead = function(v) {
	if (v.substr(0,5) == "data:" && (v = v.substr(0,256)).indexOf(",") != -1) {
		var c = v.substr(5).split(",").shift().split(";");
		var rv = {"mime":{},"encoding":c.pop(),"dataPos":v.indexOf(",")+1};
		rv.mime.full = c[0];
		c = c.shift().split("/");
		rv.mime.type = c[0];
		rv.mime.subType = c[1];
		if (c[2]) rv.mime.extra = c[2];
		return rv;
	}
	return false;
};
	
sl.jsonDecode = function(o) {
	if (global.window && window.JSON) {
		try {
			o = JSON.parse(o);
		} catch (e) {
			console.log(o);
		}
	} else {
		try {
			eval("o = "+o);
		} catch(e) {
			return o;
		};
	}
	sl.decodeExtendedData(o);
	return o;
};

sl.decodeExtendedData = function(o,i) {
	var v = i != undefined ? o[i] : o;
	switch (typeof(v)) {
		case "string":
			var dec;
			if (dec = sl.extendedObjects.decode(v)) {
				o[i] = dec;	
			}
			break;
		
		case "object":
			for (var i in v) {
				sl.decodeExtendedData(v,i);
			}
			break;
	}
};

sl.extendedObjects = {
	"encode":function(value) {
		var type = false, subType = "plain", encoding = "base64", charset = "utf8";
		if (value.buffer && value.buffer instanceof ArrayBuffer) {
			type = "ArrayBuffer";
			charset = false;
			var t = ['Int8','Uint8','Int16','Uint16','Int32','Uint32','Float32','Float64'];
			var r;
			for (var i in t) {
				eval('r = value instanceof '+t[i]+'Array;');
				if (r) {
					subType = t[i]+"Array";
					break;
				}
			}
		} else if (value.nodeName == "CANVAS") {
			return "data:image/png/canvas"+value.toDataURL().substr(14);
		} else if (value.nodeName == "IMG") {
			var canvas = document.createElement('canvas');
			canvas.setAttribute('width',value.width);
			canvas.setAttribute('height',value.height);
			canvas.getContext('2d').drawImage(image,0,0);
			return canvas.toDataURL();
		}
		if (!type) return false;
		
		switch (encoding) {
			case "base64":
				return "data:"+type+"/"+subType+";"+(charset?"charset="+charset+";":"")+"base64,"+sl.base64ArrayBufferEncode(value);
			
			default:
				switch (encoding) {
					case "escape":
						value = escape(value);
						encoding = "";
						break;
				}
				return "data:"+type+"/"+subType+";"+(charset?"charset="+charset+";":"")+(encoding?encoding+",":"")+value;
		}
	},
	"decode":function(string) {
		if (typeof(string) != "string" || string.substr(0,5) != "data:") return null;

		var data = string.substr(5).split(",",2);
		var params = data[0].split(";");
		var type = params[0].split("/");
		if (params.length <= 2) return string;
		
		var charset = params[1].substr(0,8) == "charset=" ? params.splice(1,1).substr(8) : "";
		var encoding = params[1];
		switch (type[0]) {
			case "ArrayBuffer":				
				var rv;
				try {
					eval('rv = new '+type[1]+'(sl.base64ArrayBufferDecode(data[1]))');
					return rv;
				} catch (e) {}
				return false;
				
			case "image":
				try {
					var image = new Image();
					if (type[2] == "canvas") {
						image.src = string.substr(0,14)+string.substr(21);
						var canvas = document.createElement('canvas');
						image.onload = function() {
							canvas.setAttribute('width',image.width);
							canvas.setAttribute('height',image.height);
							canvas.getContext('2d').drawImage(image,0,0);
						}
						return canvas;
					}
					image.src = string;
					return image;
				} catch (e) {}
				return string;
		}
		return false;
	}
};

sl.removeChildNodes = function(d,except) {
	if (except && (typeof(except) == "object" || typeof(except) == "string")) {
		if (!(except instanceof Array)) except = [except];
	} else {
		except = [];
	}
	var i = 0;
	while (d.childNodes.length > i) {
		if (except.indexOf(d.childNodes[i]) == -1 && except.indexOf(d.childNodes[i].nodeName) == -1 && except.indexOf("#"+d.childNodes[i].id) == -1 && except.indexOf("."+d.childNodes[i].className) == -1) {
			d.removeChild(d.childNodes[i]);
		} else {
			i++;
		}
	}
};

sl.getChildNodes = function(node) {
	var rv = [];
	if (node.childNodes) {
		for (var i = 0; i < node.childNodes.length; i++) {
			rv.push(node.childNodes[i]);
			var c;
			if (c = sl.getChildNodes(node.childNodes[i])) {
				for (var j = 0; j < c.length; j++) {
					rv.push(c[j]);
				}
			}
		}
	}
	return rv;
};

sl.isHidden = function(el) {
	while (el.parentNode && el.parentNode.style) {
		if (el.style.display == "none" || el.style.visibility == "hidden") return true;
		el = el.parentNode;
	}
	return false;
};

sl.innerSize = function(el) {
	var style = window.getComputedStyle(el);
	var width = el.offsetWidth, height = el.offsetHeight;
	
	var t = ["padding-[n]","border-[n]-width"];
	
	for (var i = 0; i < t.length; i++) {
		width -= sl.toPx(style.getPropertyValue(t[i].replace("[n]","left"))) + sl.toPx(style.getPropertyValue(t[i].replace("[n]","right")));
		height -= sl.toPx(style.getPropertyValue(t[i].replace("[n]","top"))) + sl.toPx(style.getPropertyValue(t[i].replace("[n]","bottom")));
	}
	
	return {
		"width":width,
		"height":height
	};
};

sl.getTotalElementSize = function(el, justExcess) {
	var style = window.getComputedStyle(el);
	var width = justExcess ? 0 : el.offsetWidth, height = justExcess ? 0 : el.offsetHeight;
	
	var t = ["padding-[n]","border-[n]-width","margin-[n]"];
	
	for (var i = 0; i < t.length; i++) {
		if (!(style.getPropertyValue("box-sizing") == "border-box" && t <= 1)) {
			width += sl.toPx(style.getPropertyValue(t[i].replace("[n]","left"))) + sl.toPx(style.getPropertyValue(t[i].replace("[n]","right")));
			height += sl.toPx(style.getPropertyValue(t[i].replace("[n]","top"))) + sl.toPx(style.getPropertyValue(t[i].replace("[n]","bottom")));
		}
	}
	
	return {
		"width":width,
		"height":height
	};
};

sl.toPx = function(v) {
	return Number(v.replace(/[^\d]+/gi, ""));
};

sl.getElementPosition = function(anchorElement,anchorPoint,element) {
	
	if (anchorPoint === undefined) anchorPoint = "left,top";
	var elementSize = element ? sl.getTotalElementSize(element) : null;
		
	var ap = anchorPoint.split(",");

	var anchorElementSize = sl.getTotalElementSize(anchorElement);
	var w = anchorElementSize.width, h = anchorElementSize.height;
	var nTop = 0, nLeft = 0;
	
	switch (ap[0]) {
		case "left":
			nLeft = -(element ? elementSize.width : 0);
			break;
				
		case "center":
			nLeft = Math.round(w / 2);
			break;
			
		case "right":
			nLeft = w;
			break;
	}
	
	switch (ap[1]) {
		case "top":
			nTop = -(element ? elementSize.height : 0);
			break;
			
		case "center":
			nTop = Math.round(h / 2);
			break;
			
		case "bottom":
			nTop = h;
			break;
			
		case "from-bottom":
			nTop = h - (element ? elementSize.height : 0);
			break;
	}
	
	if (anchorElement) {
		do {
			var style = window.getComputedStyle(anchorElement);
			if (element && (style.position == "absolute" || style.position == "relative")) break;
			nLeft += anchorElement.offsetLeft;
			nTop += anchorElement.offsetTop;
			anchorElement = anchorElement.offsetParent;
		} while(anchorElement);
	}
	return {"x":nLeft,"y":nTop};
};

sl.getElementXYWH = function(el) {
	var pos = sl.getElementPosition(el);
	return {"x":pos.x,"y":pos.y,"w":el.offsetWidth,"h":el.offsetHeight};
};

sl.centerInParent = function(el) {
	el.style.position = "absolute";
	var size = sl.getTotalElementSize(el);
	el.style.left = Math.round((el.parentNode.offsetWidth - size.width) / 2) + "px";
	el.style.top = Math.round((el.parentNode.offsetHeight - size.height) / 2) + "px";
};

sl.fireEvent = function(el,eventType,params,connectOrigin) {
	var event;
  if (document.createEvent) {
    event = document.createEvent("HTMLEvents");
    event.initEvent(eventType, true, true);
  } else {
    event = document.createEventObject();
    event.eventType = eventType;
  }

  //event.eventName = eventName;
 
	if (connectOrigin) event.originEvent = params;
	if (params) {
		for (var i in params) {
			event[i] = params[i];
		}
	}

  if (document.createEvent) {
    el.dispatchEvent(event);
  } else {
    el.fireEvent("on" + event.eventType, event);
  }
}

sl.touch = {"listener":null,"touched":[]};

sl.addEventListener = function(el,eventName, eventHandler, useCapture) {
	var i;
	if ((i = ["mousedown","mouseup","mousemove"].indexOf(eventName)) != -1) {
		var touchEvent = (["touchstart","touchend","touchmove"])[i];
		el.addEventListener(touchEvent, function(e) {

			var t = e.touches.length ? e.touches[0] : el.lastTouch;
			if (t) {
				el.lastTouch = t;
				sl.mouseCoords(t);
				var conv = ["offsetX","offsetY","clientX","clientY","pageX","pageY","screenX","screenY"];
				for (j = 0; j < conv.length; j++) {
					e[conv[j]] = t[conv[j]];
				}
				sl.fireEvent(el,eventName,e,t.target == el);
			}
		}, false);
	}
	
  if (el.addEventListener){
    return el.addEventListener(eventName, eventHandler, useCapture); 
  } else if (el.attachEvent){
    return el.attachEvent('on'+eventName, eventHandler);
  }
};

sl.scriptStatus = {};
sl.require = function(scripts,cb,loadAsData,params) {
	var toLoad = 0, failed = [], data = {};

	function loaded(f,src) {
		if (f) {
			failed.push(src);
			sl.scripts[src] = null;
			sl.scriptStatus[src] = 2;
		} else {
			sl.scriptStatus[src] = 1;
		}
		
		if (loadAsData) {
			data = sl.defaultValues(data,sl.data[src.split("?").shift().replace(".js","")]);
		}
		
		toLoad--;
		if (toLoad == 0 && cb) {
			if (loadAsData) {
				cb(data);
			} else {
				cb(failed.length ? failed : false);
			}
		}
	}
	
	if (typeof(scripts) != "object") scripts = [scripts];
	for (var i = 0; i < scripts.length; i++) {
		var src = scripts[i];
		if (!sl.scripts[src]) {
			sl.scriptStatus[src] = 0;
			(function(src){
				var ext = src.split(".").pop().split("?").shift();
				var el = null;
				var fullSrc = (sl.config.core.fromAPI ? sl.config.root : "")+src+(loadAsData?(src.indexOf("?") == -1 ? "?" : "&")+"lad=1":"");
				
				switch (ext) {
					case "js": case "json":				
						el = document.createElement("script");
						el.type = "text/javascript";
						el.src = fullSrc;
						document.body.appendChild(el);
						break;
					
					case "css":
						el = document.createElement("link");
						el.rel  = 'stylesheet';
						el.media = "all";
						el.type = "text/css";
						el.href = fullSrc;
						document.head.appendChild(el);
						break;
				}
				
				if (params) el.params = params;
				el.addEventListener("load",function(){loaded(false,src);},false);
				el.addEventListener("error",function(){loaded(true,src);},false);
				
				sl.scripts[src] = el;
			})(src);
			toLoad++;
		} else if (sl.scriptStatus[src] == 0) {
			toLoad++;
			sl.scripts[src].addEventListener("load",function(){loaded(false,src);},false);
			sl.scripts[src].addEventListener("error",function(){loaded(true,src);},false);			
		}
	}
	if (toLoad == 0 && cb) {
		if (loadAsData) {
			cb(sl.defaultValues(data,sl.data[scripts[0].split("?").shift().replace(".js","")]));
		} else {
			cb(false);
		}
	}
};

sl.loadAsData = function(scripts,cb,params) {
	sl.require(scripts,cb,true,params);
};

sl.parseLink = function(link) {
	return sl.config.core.fromAPI ? sl.config.root + link : link;
};

sl.highlightMatch = function(full,sub,enc) {
	if (!enc) enc = ["<b>","</b>"];
	
	var fullM = full.replace(/([^\w\d])/gi," ").toLowerCase().charNormalize();
	sub = sub.replace(/([^\w\d])/," ").toLowerCase().charNormalize();

	var i;
	if ((i = fullM.indexOf(sub)) != -1) {
		return full.substr(0,i)+enc[0]+full.substr(i,sub.length)+enc[1]+full.substr(i+sub.length);
	}
	
	return full;
};

sl.shortNum = function(v) {
	var n = 0;
	var t = ["","K","M","B","T"];
	while (v > 1000) {
		n++;
		v = v / 1000;
	}
	return v.format("#,###.##")+t[n];
};

sl.bytesFormat = function(v) {
	var n = 0;
	var t = ["B","KB","MB","GB","TB"];
	while (v > 1000) {
		n++;
		v = v / 1000;
	}
	return v.format("#,###.##")+t[n];
}



sl.getDeepRef = function(ob,r,delim) {
	if (r === "") return ob;
	if (delim === undefined) delim = ".";
	if (typeof(r) == "string") { r = r.split(delim); }
	var n = r.shift();
	if (ob[n] !== undefined) {
		if (r.length) {
			if (typeof(ob[n]) == "object") {
				return sl.getDeepRef(ob[n],r,delim);
			} else return undefined;
		} else {
			return ob[n];
		}
	}
	return undefined;
};

sl.setDeepRef = function(ob,r,v,delim) {
	if (typeof(ob) != "object") return;
	if (r === "") return ob = v;
	if (delim === undefined) delim = ".";
	if (typeof(r) == "string") { r = r.split(delim); }
	var n = r.shift();

	if (r.length) {
		if (!ob[n]) ob[n] = {};
		sl.setDeepRef(ob[n],r,v,delim);
	} else {
		ob[n] = v;
	}
	return false;
};

sl.defaultValues = function(o,def) {
	if (o == undefined) o = {};
	for (var i in def) {
		if (o[i] == undefined) {
			o[i] = def[i];
		}		
	}
	return o;
};

sl.delimToObject = function(s,parts,delim) {
	if (!delim) delim = ";";
	var delimEnc = escape(";"), rv = {};
	s = s.split(delim);
	for (var i = 0; i < Math.min(s.length,parts.length); i++) {
		rv[parts[i]] = s[i].split(delimEnc).join(delim);
	}
	return rv;
};


sl.objectToDelim = function(o,parts,delim) {
	if (!o) o = {};
	if (!delim) delim = ";";
	var delimEnc = escape(";"), rv = [];

	for (var i = 0; i < parts.length; i++) {
		rv.push(o[parts[i]] === undefined ? "" : o[parts[i]].split(delim).join(delimEnc));
	}
	while (rv.length && rv[rv.length - 1].trim() == "") {
		rv.pop();
	}
	return rv.join(delim);
};

sl.unescapeObject = function(o) {
	for (var i in o) {
		if (typeof(o[i]) == "object") {
			sl.unescapeObject(o[i]);
		} else if (typeof(o[i]) == "string") {
			o[i] = unescape(o[i]);
		}
	}
	return o;
};

sl.supports = function(n) {
	switch (n) {
		case "canvas":
			var elem = document.createElement('canvas');
			return !!(elem.getContext && elem.getContext('2d'));
	}
	return false;
};

sl.getTZ = function(name) {
	if (!sl.data.timezone) return null;
	var tz;
	name = name.searchify();
	for (var i = 0, len = sl.data.timezone.length; i < len; i++) {
		tz = sl.data.timezone[i];
		if (name == tz[0].searchify() || name == tz[1].searchify()) return {"name":tz[0],"abbreviation":tz[1],"offset":tz[2]*3600};
	}
	return null;
};

sl.getLocalTime = function(timezone,format,ts) {
	if (!sl.data.timezone) return "en-us|N/A";
	if (!ts) ts = sl.unixTS();
	if (!format) format = sl.config.international.time;
	var tz;
	if (tz = sl.getTZ(timezone)) {
		var utc = ts + (new Date()).getTimezoneOffset() * 60;
		return sl.date(format,utc + tz.offset);
	}
	return "en-us|N/A";
};

sl.tGIF = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";

sl.addEventListener(window,"load",function(){

  var inner = document.createElement('p');
  inner.style.width = "100%";
  inner.style.height = "200px";

  var outer = document.createElement('div');
  outer.style.position = "absolute";
  outer.style.top = "0px";
  outer.style.left = "0px";
  outer.style.visibility = "hidden";
  outer.style.width = "200px";
  outer.style.height = "150px";
  outer.style.overflow = "hidden";
  outer.appendChild (inner);

  document.body.appendChild (outer);
  var w1 = inner.offsetWidth;
  outer.style.overflow = 'scroll';
  var w2 = inner.offsetWidth;
  if (w1 == w2) w2 = outer.clientWidth;

  document.body.removeChild (outer);

  sl.scrollBarWidth = (w1 - w2);
 
},false);

sl.refEncode = function(o) {
	function encodeItem(o) {
		switch (sl.typeOf(o)) {
			case "array":
				var rv = [];
				for (var i = 0; i < o.length; i++) {
					rv.push(escape(encodeItem(o[i])));
				}
				return rv.join("&");
				
			case "object":
				var rv = [];
				for (var n in o) {
					rv.push(encodeItem(n)+"="+escape(encodeItem(o[n])));
				}
				return rv.join("&");
				
			case "string":
			case "number":
				return escape(o.toString()).split("FALSE").join("%46ALSE").split("TRUE").join("%54RUE");
				
			case "boolean":
				return o?"TRUE":"FALSE";
			
			case "null":
				return "NULL";
			
			default:
				console.log(sl.typeOf(o));
				return "";
		}
	};
	return encodeItem(o);
};

sl.refDecode = function(o,interpretAsArray) {
	function decodeItem(o,iar) {
		var n;
		if (o == "NULL") {
			return null;
		} else if (o == "TRUE") {
			return true;
		} else if (o == "FALSE") {
			return false;
		} else if (o.indexOf("=") != -1) {
			o = o.split("&");
			var rv = {};
			for (var i = 0; i < o.length; i++) {
				var b = o[i].split("=");
				rv[unescape(b[0])] = decodeItem(unescape(b[1]));
			}
			return rv;
		} else if (o.indexOf("&") != -1 || iar) {
			o = o.split("&");
			var rv = [];
			for (var i = 0; i < o.length; i++) {
				rv.push(decodeItem(unescape(o[i])));
			}
			return rv;
		} else if ((n = o.match(/\-?[\d\.]+/)) && n == o) {
			return Number(o);
		}
		return unescape(o);
	};
		
	return decodeItem(o,interpretAsArray);
};

(function(){
	function gns(n){return [n,"webkit"+n.ucFirst(),"moz"+n.ucFirst(),"o"+n.ucFirst(),"ms"+n.ucFirst()]};
	
	sl.getNonStadardsItem = function(o,n) {
		var ns = gns(n);
		while (n = ns.shift()) {
			if (o[n] !== undefined && o[n] !== null) return o[n];
		}
		return null;
	};

	sl.getNonStadardsItemName = function(o,n) {
		var ns = gns(n);
		while (n = ns.shift()) {
			if (o[n] !== undefined && o[n] !== null) return n;
		}
		return false;
	};

	sl.setNonStadardsItem = function(o,n,v) {
		var ns = gns(n);
		while (n = ns.shift()) {
			if (o[n] !== undefined && o[n] !== null) {
				o[n] = v;
				return true;
			}
		}
		return false;
	};
})();

sl.parseExpression = function(exp,params) {
	if (exp.charAt(0) == "=") {
		exp = exp.substr(1);
		for (var n in params) {
			exp = exp.split(n+".").join("params."+n+".");
		}
		eval("exp = "+exp);
	}
		
	return exp; 
};

sl.initContentForElement = function(el,listener,params,self,parentView) {
	var c = sl.getChildNodes(el);
	c.push(el);
	for (var i = 0; i < c.length; i++) {
		var validate;
		if (c[i].getAttribute) {
			if (validate = c[i].getAttribute("data-validate")) {
				c[i].slValidator = new sl.fieldValidator({"field":c[i],"view":self,"core":self.core,"rules":validate});
				parentView.specialElements.push(c[i].slValidator);
			}
			
			if (c[i].getAttribute("data-field")) {
				var field = {
					"core":parentView.core,
					"view":parentView,
					"listener":listener
				};					
				
				if (["INPUT","SELECT","TEXTAREA"].indexOf(c[i].nodeName) != -1) {
					field.el = c[i];
				} else {
					field.el = null;
					field.contEl = c[i];
				}
				
				for (var attr, j = 0, attrs = c[i].attributes, l = attrs.length; j < l; j++){
					var a = ["type","value","n","cleaners","style","ref","useID","readOnly","readOnlyField","indexIsValue","definition","label","options"], ip, alc = [];
					for (var k = 0; k < a.length; k++) { alc.push(a[k].toLowerCase()); }
					if (attrs.item(j).nodeName.substr(0,5) == "data-" && (ip = alc.indexOf(attrs.item(j).nodeName.substr(5))) != -1) {
						field[a[ip]] = attrs.item(j).value;
					}
				}
				
				if (!field.n) field.n = c[i].getAttribute("data-slid");
				if (!field.type) field.type = c[i].getAttribute("data-field");
				
				if (!c[i].slSpecial) {
					sl.removeChildNodes(c[i],"LABEL");
					sl.dg("",field.contEl,"label",{"innerHTML":field.label});
					c[i].slSpecial = new sl.field(sl.defaultValues(field,params));
				}
			}
		}
		
		//TODO: This was causing issues
		/*
		if (["INPUT","TEXTAREA"].indexOf(c[i].nodeName) != -1 && !self.focusField) {
			parentView.focusField = c[i];
			parentView.focusField.focus();
		}
		*/
		
		switch (c[i].className) {
			case "tabbed": case "repeater": case "scroller":
			case "sizeable-layout": case "foldable-section":
				var p2 = {"el":c[i],"view":parentView};
				
				for (var attr, j = 0, attrs = c[i].attributes, l = attrs.length; j < l; j++){
					if (attrs.item(j).nodeName.substr(0,5) == "data-") {
						var n = attrs.item(j).nodeName.substr(5).toCamelCase();
						if (n == "slid") n = "id";
						p2[n] = attrs.item(j).value;
					}
				}
				
				if (!c[i].slSpecial) parentView.specialElements.push(new sl[c[i].className.toCamelCase()](p2));
				break;
		}
		
		if (c[i].href && c[i].href.substr(0,8) == "sl-open:" && c[i].href.indexOf("%") == -1) {
			(function(el,ref){
				el.addEventListener("click",function(){
					parentView.core.open(ref);
				});
			})(c[i],c[i].href.substr(8));
			c[i].href = "javascript:;";
		}
		
		if (c[i].className && c[i].className == "no-item") {
			var table = c[i], tr = null, tbody = null;
			while (table && table.nodeName != "TABLE") {
				if (table.nodeName == "TR") tr = table;
				if (table.nodeName == "TBODY") tbody = table;
				table = table.parentNode;
			}
			if (table && tr) {
				table.noItem = {"el":c[i],"tbody":tbody?tbody:table,"tr":tr,"show":false};
				c[i].parentNode.removeChild(c[i]);
				parentView.updateNoItemMessage(table.getAttribute("data-slid"));
			}
		}
	}
};
	

Number.prototype.format = function(f) {
	var cs = f.match(/(\.|\,)/)[0] == ",";
	var dp = f.split(cs?".":",").pop().length;
	
	var v = this.toString().split(".");
	if (v.length == 1) v.push("0");
	v[0] = v[0].toString();
	
	while (v[1].length < dp) {
		v[1] += "0";
	}
	
	v[1] = v[1].substr(0,dp); //TODO: round
	
	return (function(v){
		var rv = [];
		for (var i = 0; i < v.length; i+=3) {
			var d = Math.max(0,v.length-(i+3));
			rv.unshift(v.substr(d,3-(d - (v.length-(i+3)))));
		}
		return rv.join(cs?",":".");
	})(v[0])+(dp?(cs?".":",")+v[1]:"");
};

sl.onAllLoaded = function(el,cb) {
	var toLoad = 0;
	var c = sl.getChildNodes(el);
	for (var i = 0; i < c.length; i++) {
		if (c[i].nodeType == 1) {
			if (c[i].nodeName == "IMG" && !c[i].complete) {
				toLoad ++;
				c[i].addEventListener("load",function(){
					toLoad --;
					if (toLoad == 0) cb();
				});
			}
		}
	}
	return toLoad > 0;
};

sl.chainer = function() {
	var stack = [], cur = 0;
	
	function next() {
		if (stack[cur]) {
			var thisArg = stack[cur].shift();
			var func = stack[cur].shift();
			func.apply(thisArg,stack[cur]);
			cur++;
		}
	};
	
	for (var i = 0; i < arguments.length; i++) {
		arguments[i].push(next);
		stack.push(arguments[i]);
	}
	
	next();	
};

sl._CHAIN = function() {
	var i = 1, arg = arguments, timedOut = [];
	
	function nextStep(v) {
		var f = arg[i++];
		
		switch (typeof(f)) {
			case "function":
				var rv, nsCB;
				
				(function(i){
					nsCB = function(v,timeOut){
						if (!timeOut && timedOut.indexOf(i) != -1) return;
						nextStep(v);
					};
				})(i);
				
				if (sl.typeOf(v) == "array") {
					v.push(nsCB);
					rv = f.apply(this,v);
				} else {
					rv = f(v,nsCB);
				}
				if (sl.isASYNC(rv)) {
					setTimeout(function(){
						timedOut.push(i);
						nsCB({"success":false,"e":{"text":"sl ASYNC timeout"}}, true);
					},rv.timeout);
				} else if (rv !== undefined) {
					nsCB(rv);
				}
				break;
				
			case "number":
				setTimeout(function(){nextStep(v);},f);
				break;
		}
	};
	nextStep(arguments[0]);
};

sl._ASYNC = function(timeout) {
	return {"sl_ASYNC":true,"timeout":timeout};
};

sl.isASYNC = function(o) {
	return sl.typeOf(o) == "object" && o.sl_ASYNC;
};

(function() {
	sl.rest = {
		"GET":function(url,cb){
			sl.rest.request({"type":"GET","url":url,"cb":cb});
		},
		"go":function(url,params,options,cb){
			options = sl.defaultValues(options,{"send":"JSON","receive":"JSON"});
			var post = "";

			switch (options.send) {
				case "POST":
				case "GET":
					var p = [];
					for (var n in params) {
						p.push(n+"="+escape(params[n]).split("%20").join("+"));
					}
					if (p.length) {
						if (options.send == "GET") {
							url = url+"?"+p.join("&");
						} else {
							post = p.join("&");
						}
					}
					break;
					
				case "JSON":
					post = escape(JSON.stringify(params));
					break;
			}
			
			sl.rest.request({"type":options.send!="GET"?"POST":"GET","url":url,"post":post,"cb":function(res){
				if (res.success) {
									
					res.res = (function(){
						switch (options.receive) {
							case "JSON":
								return JSON.parse(res.res.responseText);
								
							default:
								return res.res.responseText;
						}
					})();
				}
				
				cb(res);
			}});
			return sl._ASYNC(60000);
		},
		"request":function(req){
			var conn = sl.coreOb.net.newHTTPRequest(),
				cbTimer = null;

			function cb(r) {
				if (conn.complete) return;
				if (cbTimer) clearTimeout(cbTimer);
				cbTimer = setTimeout(function(){
					conn.complete = true;
					req.cb(r);
				},500);
			};
			
			conn.complete = false;
			conn.size = req.post ? req.post.length : 0;
			conn.start = sl.unixTS(true);

			conn.onreadystatechange = function() {
				switch (conn.readyState) {
					case 4:
						switch (String(conn.status).substr(0,1)) {			
							case "0":
								failed();
								break;
													
							case "2":
								cb({"success":true,"res":conn});
								break;							
								
							default:
								self.error("httpSend response: "+conn.status);
								break;
						}
						break;
				}
			};

			function failed(e) {
				cb({"success":false,"res":conn,"e":e});
			};
			
			conn.onprogress = function(e) {
				//console.log("progress",e);
			};
			
			conn.onerror = function(e) {
				failed(e);
				//console.log("error",e);
			};

			conn.onabort = function(e) {
				failed(e);
				//console.log("abort",e);
			};
			
			try {
				conn.open(req.type, req.url, true, req.user ? requser : undefined, req.password ? req.password : undefined);
				conn.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

				/*(conn.timeoutTimer = setTimeout(function() {
					conn.abort();
				},10000);*/
				
				conn.send(req.post);
			} catch (e) {
					failed(e);
			}			
		}
	};
})();

sl.quickHash = function(o) {
	function shortHash(s) {
		var i, hash = 0;
		for (i = 0; i < s.length; i++) {
			hash = ((hash<<5)-hash)+s.charCodeAt(i);
			hash = hash & hash;
		}
		var rv = "";
		for (i = 0; i < 32; i+= 4) {
			rv += ((hash>>i)&15).toString(16);
		}
		return rv;
	}
	
	o = JSON.stringify(o);
	var str = "b2dcc674bdae53e00fbd3ee2e0e3826749ab9a8ca46db644ed3a5e84adcfe3079226277164218fb8", i = 0;
	while (o.length < 32) {
		o += String.fromCharCode(parseInt(str.substr(i,2), 16));
		i += 2;
	}
	
	var pos = 0, len = o.length>>2;
	return shortHash(o.substr(0,len))+shortHash(o.substr(pos+=len,len))+shortHash(o.substr(pos+=len,len))+shortHash(o.substr(pos+=len));
};

sl.obLength = function(ob) {
	var len = 0;
	for (var n in ob) {
		len++;
	}
	return len;
};
