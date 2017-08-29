function selectTree(containerEl,tree) {
	var self = this, initialized = false;

	self.buildTree = function(tree,parent,level) {
		function check(button,cb,level,setChecked) {
			cb.checked = setChecked;
			button.className = buttonClass[level]+" btn-block"+(cb.checked?" btn-primary":" btn-default");
		};
		
		var buttonClass = ["btn btn-lg","btn"];
		if (parent === undefined) parent = containerEl;
		if (level === undefined) level = 0;
			
		for (var n = 0; n < tree.length; n++) {
			(function(branch){
				function clicked(e) {
					if (!e) return;
					if (e !== true) {
						e.stopPropagation();
					}
					
					check(button,cb,level,!cb.checked);
					if (e && branch.children) {
						for (var n2 = 0; n2 < branch.children.length; n2 ++) {
							var child = branch.children[n2];
							check(child.button, child.cb, level + 1, cb.checked);
							child.button.style.display = cb.checked ? "" : "none";
						}
					}
					return false;
				};
				
				var button = branch.button = dg("",parent,"button",{"type":"button","style":{"margin-bottom":"4px","display":(level==0 || branch.selected ?"":"none"),"fontWeight":(level==0?"bold":"")},"className":buttonClass[level]+" btn-default btn-block"});
				var hidden = dg("",parent,"input",{"type":"hidden","name":"id-"+branch.id,"value":1});
				var cb = branch.cb = dg("",button,"input",{"name":branch.id,"type":"checkbox","selected":!!branch.selected});
				var text = dg("",button,"text"," "+branch.label);
				
				cb.addEventListener("change",function(e){e.preventDefault();clicked(e);},true);
				cb.addEventListener("click",function(e){e.preventDefault();},true);
				
				button.addEventListener("click",function(e){e.preventDefault(); clicked(e); },true);
							
				if (branch.children) {
					self.buildTree(branch.children, parent, level + 1);
					dg("",parent,"div",{"style":{"height":"20px"}});
				}
				
				if (branch.selected) clicked(true);
			})(tree[n]);
		}
	};
	
	self.buildTree(tree);
};
