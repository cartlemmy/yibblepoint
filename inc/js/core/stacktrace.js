sl.stackTrace = function(e) {
	var mode = "firefox";
	
	function generateE() {
		try {
			this.undef();
		} catch (e) {
			return e;
		}
	};
	
	function parseStackItem(item) {
		if (!item || typeof(item) != "string") return false;
		var rv = {}, match;

		if (match = item.match(/^\s+at/)) {
			var o = item.substr(match[0].length).split("(",2);
			if (o.length == 1) {
				o.unshift("");
			}

			rv.func = o[0].trim();
			
			var s = o[1].split(")").shift().split(":");
			
			s.pop();
			rv.line = Number(s.pop());
			rv.script = s.join(":").trim();
			
		} else {
			var o = item.split("@",2);
			if (o.length == 1) return false;
			
			rv.func = o[0];
			
			var s = o[1].split(":");
			
			rv.line = Number(s.pop());
			rv.script = s.join(":");
		}
		
		if (rv.script.indexOf("?") != -1) {
			rv.script = rv.script.split("?",2);
			rv.scriptParams = rv.script.pop().multiSplit("&","=");
			rv.script = rv.script[0];
			
			if (rv.scriptParams.aid) {
				rv.app = global.handles.app[Number(rv.scriptParams.aid)];
				if (rv.scriptParams.mn) {
					rv.appModule = rv.app.modules[Number(rv.scriptParams.mn)];
					delete rv.scriptParams.mn;
				}
				delete rv.scriptParams.aid;
			}
		}
		return rv;
	};
	
	function getStack() {
		if (!e.stack) return null;
		var stack = typeof(e.stack) == "string" ? e.stack.split("\n") : e.stack;
		var rv = [];
		
		if (stack[1].match(/^\s+at/)) {
			stack.shift();
			mode = "chrome";
		}
		
		for (var i = 0; i < stack.length; i++) {
			var res = parseStackItem(stack[i]);
			if (res && res.script.indexOf("/stacktrace.js") == -1) {
				rv.push(res);
			}
		}
				
		return rv;
	};
	
	if (!e) e = generateE();
	
	return getStack();
};
