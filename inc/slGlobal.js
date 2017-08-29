var sl = {
	"data":{},
	"scripts":{},
	"toLoad":[],
	"loadScript":function (src) {
		if (!sl.scripts[src]) {
			sl.scripts[src] = {"src":src,"el":null};
			sl.toLoad.push(sl.scripts[src]);
		}
	}
}, global = window;
global.handles = [];
