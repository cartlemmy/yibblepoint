sl.heirarchicalView = function(o) {
	var self = this;
	var cn;
	
	sl.initSlClass(this,"heirarchical-view");

	self.init = function() {
		if (self.isInit) return;
	
		if (self.data) build(self.el,self.data);
		
		self.isInit = true;
	};
	
	self.setChildrenName = function(n) {
		cn = n;		
	};
	
	self.setData = function(data) {
		self.data = data;
		if (data && self.callback) self.refresh();
	};
	
	self.refresh = function() {
		sl.removeChildNodes(self.el);
		build(self.el,self.data);
	};

	function build(el,data,notTop,tree) {
		tree = tree == undefined ? [] : tree.split(".");
		
		var ul = sl.dg("",el,"ul",{"className":"tree"});
		for (var n in data) {
			tree.push(n);
			var id = tree.join(".");
			
			var li = sl.dg("",ul,"li",{"className":(data[n][cn]?"collapsed":"")});
			var el = self.callback(n,data[n],id);
			li.appendChild(el);
			
			data[n].li = li;
			data[n].el = el;
			data[n]._ID = id;
			
			self.map[id] = data[n];
			
			(function(n,id){
				if (data[n][cn]) {
					data[n].childrenUl = build(li,data[n][cn],true,id);
					self.expand(id,false);
				}
				li.addEventListener("click",function(e){
					self.expand(id,!data[n].expanded);
					sl.cancelBubble(e);
				});
					
				
				if (self.clickable) {
					el.style.cursor = "pointer";
					el.addEventListener("click",function(e){
						sl.cancelBubble(e);
						self.dispatchEvent("click",n,data[n]);
					});
				}	
								
			})(n,id);
			tree.pop();
		}
		return ul;
	};
	
	self.add = function(parent,n,data) {
		var id = parent;
		if (parent === "") {
			parent = self.data;
		} else {
			parent = self.map[parent];
			if (!parent) return false;
		}
		if (!parent[cn]) {
			parent[cn] = {};
			parent[cn][n] = data;
			parent.childrenUl = build(parent.el,parent[cn],true,id);
			parent.li.className = "collapsed";
			self.expand(id,false);
		}
		
		return true;
	};
	
	self.remove = function(n) {
		var node = self.getDataNodeByName(n);
		var parent = self.parentNode(n);
		
		delete parent[cn][n.split(".").pop()];
		
		node.li.parentNode.removeChild(node.li);
		delete self.map[n];
		self.updateNode(parent);
	};
	
	self.expand = function(n,expand) {
		var node = self.getDataNodeByName(n);
		
		node.expanded = expand;
		
		self.updateNode(node);
	};
	
	self.parentNode = function(n) {
		n = n.split(".");
		n.pop();
		return self.getDataNodeByName(n.join("."));
	}
	
	self.updateNode = function(node) {		
		if (!node) return;
		if (node.childrenUl) node.childrenUl.style.display = node.expanded ? "" : "none";
		
		var c = [];
		if (sl.obLength(node[cn])) c.push(node.expanded ? "expanded" : "collapsed");
		node.li.className = c.join(" ");
		
		node.el.className = node._ID == self.selected ? "selected" : "";
	}
	
	self.select = function(id) {
		var old = self.selected;
		self.selected = id;
		self.updateNode(self.getDataNodeByName(old));
		self.updateNode(self.getDataNodeByName(id));
	};
	
	function dataNodeSearch(nFind,data) {
		var r;
		for (var n in data) {
			if (n == nFind) return data[n];
			if (data[n][self.childrenName]) {
				if (r = dataNodeSearch(nFind,data[n][cn])) return r;
			}
		}
		return null;
	};
	
	self.getDataNodeByName = function(n) {
		if (self.map[n]) return self.map[n];
		if (typeof(n) == "string" || typeof(n) == "number") {
			return dataNodeSearch(n,self.data);
		}
		return n;
	};
	
	self.getDataNodeBySearch = function(key,v,data) {
		if (!data) data = self.data;
		for (var n in data) {
			if (data[n][key] && data[n][key] == v) {
				return data[n];
			} else if (data[n][cn] && (r = self.getDataNodeBySearch(key,v,data[n][cn]))) return r;
		}
		return null;
	};
	
	self.destruct  = function() {
		
	};

	self.setValues({
		"childrenName":"children",
		"map":{},
		"selected":""
	});
	
	if (o) self.setValues(o);
	
	self.init();
};
