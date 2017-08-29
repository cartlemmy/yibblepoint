sl.onScriptLoad = function(el,cb) {
	el.slLoaded = false;
	el.onload = function() {
		if (!el.slLoaded) {
			el.slLoaded = true;
			cb();
		}
	};
	el.onreadystatechange = function(e) {
		if ((this.readyState == "loaded" || this.readyState === "complete") && !el.slLoaded) {
			el.slLoaded = true;
			cb();
		}
	};
};
		
sl.scriptLoader = function(api) {
	if (api) {
		var p = 0;
		if ((p = navigator.userAgent.indexOf("MSIE")) != -1) {
			p += 4;
			var v = Number(navigator.userAgent.substr(p,navigator.userAgent.indexOf(";",p)-p).replace(/^\s+|\s+$/g,""));
			if (!isNaN(v) && v < 9) {
				//sl.toLoad.unshift({"src":sl.config.core.IEComplianceLink,"el":null,"loaded":false});
			}
		}
		if (sl.config.noCSCookies) {
			sl.config.sessionName = "slSID";
			if (sl.cookie.hasItem("slSID")) {
				console.log("got cookie slSID ",sl.cookie.getItem("slSID"));
				sl.config.sessionId = sl.cookie.getItem("slSID");
			} else {
				sl.loadScript(sl.config.root+"slSID.js?sn="+sl.config.APIScriptName+"&key="+sl.config.APIKey);
			}
		}		
	}
	
	function loadOne() {
		if (!sl.toLoad.length) {
			sl.init();
			return;
		}
		var l = sl.toLoad.shift();
				
		if (document.getElementById('loadMessageDetail')) document.getElementById('loadMessageDetail').innerHTML = "Loading "+l.src.split("/").pop();
		
		l.el = document.createElement("script");
		l.el.type = "text/javascript";
		l.el.src = l.src;
		
		sl.onScriptLoad(l.el,loadOne);
		document.body.appendChild(l.el);
	};
		
	if (sl.toLoad.length) {
		loadOne();
	} else {
		sl.init();
	}		
	
};
