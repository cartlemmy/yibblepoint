self.setTitle("en-us|Third Party Library Check");
self.setContentFromHTMLFile();

var items = {};

function update() {
	app.request("thirdPartyStatus",[],function(data){
		for (var n in data) {
			items[n].installed = data[n].installed;
			applyStatusUpdate(items[n]);
		}
	});
}

function updateStatus(item) {
	app.request("thirdPartyStatus",[item.n],function(data){
		item.installed = data.installed;
		applyStatusUpdate(item);
	});
}

function applyStatusUpdate(item) {
	sl.removeChildNodes(item.el.status);
	item.el.statusText = sl.dg("",item.el.status,"div",{"innerHTML":item.installed?"INSTALLED":"Not Installed"});
	if (!item.installed) {
		if (!item.required) {
			//var but = sl.dg("",item.el.status,"button",{"innerHTML":"INSTALL"});
			//but.addEventListener("click",function(){install(item);});
		}
		//if (data.required && !data.installFailed) install(item)
	}
}

//className
function install(item) {
	item.el.statusText.innerHTML = "INSTALLING...";
	app.request("install",[item.n],function(res){
		console.log(res);		
	});
}

self.addEventListener("appeared",function(){
	app.request("thirdPartyStatus",[],function(data){
		var lCont = self.view.element("list");
		for (var n in data) {
			var item = data[n];

			item.n = n;
			
			item.el = {};
			
			item.el.cont = sl.dg("",lCont,"section",{"className":"box"})
			
			item.el.statusCont = sl.dg("",item.el.cont,"div",{"style":{"cssFloat":"left","minHeight":"60px","width":"180px","margin":"0 10px 10px 0"}});
			
			item.el.status = sl.dg("",item.el.statusCont,"div");
			
			if (item.required) sl.dg("",item.el.statusCont,"div",{"innerHTML":"REQUIRED","className":"important"});
			updateStatus(item);
			
			sl.dg("",item.el.cont,"h3",{"innerHTML":item.name,"style":{"display":"inline"}});
			
			sl.dg("",item.el.cont,"br");
			
			sl.dg("",item.el.cont,"span",{"innerHTML":item.desc+"<br /><a href=\""+item.url+"\">"+item.url+"</a>"});
			
			sl.dg("",item.el.cont,"div",{"style":{"clear":"both"}});
			//console.log(self.view.element("statusList").slSpecial.append(data[n]));
			//console.log(data[n]);
			
			items[n] = item;
		}
	});
});

self.view.element("continue").addEventListener("click",function(){
	app.navigate("super-user-info");
},false);

self.addEventListener("appeared",function() {
	self.statusInt = setInterval(update,5000);
});

self.addEventListener("disappeared",function() {
	if(self.statusInt) clearInterval(update);
});
