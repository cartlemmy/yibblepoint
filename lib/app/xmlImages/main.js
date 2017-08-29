sl.require(["jquery"],function(){
self.require(["xmlimages.css"],function(){
self.request("getAll",[],function(info){
	
	function delImage(image) {
		self.request("delImage",[image],function(res){
			
		});
	}
	
	function addImage(image) {
		var w, h;
		if (image.width > image.height) {
			w = 256;
			h = (image.height / image.width) * 256;
		} else {
			h = 256;
			w = (image.width / image.height) * 256;
		}
		var cont = self.view.appendHTML('images','<div><div class="im-cont"><img src="'+image.thumb+'" style="left:'+Math.round((256-w)/2)+'px;top:'+Math.round((256-h)/2)+'px"></div><button class="del">X</button></div>')[0];
		
		$(cont).find('button.del').click(function(){
			$(cont).remove();
			delImage(image);
		});
	}
	
	var i, uploadField, isOptionChild = false;
	if (info.data || self.args[1] == "NEW") {
		self.info = info;

		function getTitle() {
			var dn = typeof(info.setup.displayName) == "string" ? [info.setup.displayName] : info.setup.displayName;
			
			var displayName = "", item = info.data;
			for (i = 0; i < dn.length; i++) {
				eval("displayName = "+dn[i]);
				if (displayName) displayName = displayName.trim();
				if (displayName != "") break;
			}
			
			return "Images: "+info.setup.singleName+(displayName.trim() != ""?sl.config.sep+displayName:"");
		};
		
		
		self.createView({
			"title":getTitle(),
			"contentPadding":"0px"/*,
			"noScroll":info.setup.tabs?true:false*/
		});
		
		self.view.setContentFromHTMLFile();
								
		uploadField = new sl.field({
			"core":self.core,
			"view":self.view,
			"type":"image",
			"contEl":self.view.element('image-upload'),
			"fields":[],
			"n":'image-upload',
			"cleaners":[],
			"value":'',
			"listener":self
		});	
		
		self.addEventListener("blur",function(t,o){
			if (o.changed && o.value !== false) {
				self.request("addImage",[info.data.safeName,sl.delimToObject(o.value,["name","type","size","md5","dimensions","thumbHead","thumb","user"])],function(res){
					addImage(res);
				});
			}
		});
		
		for (i = 0; i < info.data.images.length; i++) {
			addImage(info.data.images[i]);
		}
		self.addEventListener("destruct",function() {
			if (self.serverListener) self.removeServerListener(self.serverListener);
		});
	} else {
		self.createView({
			"title":info.setup ? info.setup.singleName : "en-us|Error",
			"contentPadding":"8px"
		});
		
		self.view.setContentAsHTML("<div class=\"warn\">"+(info.setup ? sl.format("en-us|%% not found.",info.setup.singleName) : info.error)+"</div>");
	}
	
	self.view.maximize();	
});
});
});
