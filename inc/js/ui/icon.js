sl.icon = function(o) {
	var self = this;
	sl.initSlClass(this,"icon");

	self.init = function() {	
		self.el = sl.dg("",self.contEl,"div",{
			"className":"sl-icon",
			"style":{"visibility":self.startHidden ? "hidden" : ""}
		});
		self.el.addEventListener("click",function() {
			if (self.editRef) self.core.open(self.editRef+"&"+self.item._KEY);
		});
	};
	
	self.setSource = function(source) {
		if (source) {
			if (source.request) {
				self.core.net.send(source.request,source.params,{},function(res){
					self.item = res.item;
					if (res.info) {
						self.editRef = res.info.editRef ? res.info.editRef : "edit/?"+res.info.table;
						self.el.style.backgroundImage = "url('"+res.info.icon+"-24.png')";
					}
				});
			}
		}
		self.source = source;
	};
	
	self.setValues({
		"el":null,
		"editRef":"",
		"source":null
	});
	
	if (o) self.setValues(o);
	
	self.init();
};

sl.fileIcon = function(o) {
	var self = this;
	sl.initSlClass(this,"file-icon");
	
	self.init = function() {
		self.isInit = true;
		self.setSize(self.size);
		self.setFile(self.file);		
	};
	
	/*self.iconFromImage = function() {
		var thumbReader = new FileReader();
		thumbReader.onloadend = (function(e) {
			var thumb = new Image;
			thumb.src = e.target.result;
			
			setTimeout(function(){
				var canvas = document.createElement('canvas');
				canvas.setAttribute('width',self.size);
				canvas.setAttribute('height',self.size);
				
				var w,h;
				var ratio = thumb.width / thumb.height;
				var size = ratio > 1 ? thumb.height : thumb.width;
				
				var ctx = canvas.getContext('2d');
				ctx.drawImage(
					thumb,
					Math.round((thumb.width-size)/2),Math.round((thumb.height-size)/2),size,size,
					0,0,self.size,self.size
				);
				
				self.iconEl.src = canvas.toDataURL("image/jpeg",0.7);
			},50);
			
		});
		thumbReader.readAsDataURL(self.file);
	};*/
	
	self.setSize = function(size) {
		if (self.iconEl) {
			self.iconEl.style.width = self.iconEl.style.height = size+"px";
		}
		self.size = size;
	};
	
	self.setFile = function(file) {
		if (self.iconEl && self.isInit) {
			if (typeof(file) == "string") {
				//TODO
			} else {
				switch (file.type) {
					case "image/jpeg": case "image/png": case "image/gif":
						sl.require("js/ui/generateThumb.js",function(){
							sl.generateThumb(file,self.size,function(res){
								self.iconEl.src = res.thumb;
							});
						});
						//self.iconFromImage();
						break;
						
					default:
						console.log("//TODO: handle icon for file type: "+file.type);
						break;
				}
			}
		}
		self.file = file;
	};
	
	self.setEl = function(el){
		if (el) self.iconEl = sl.dg("",el,"img",{"style":{"width":self.size+"px","height":self.size+"px"}});
		self.el = el;
	}
	
	self.setValues({
		"el":null,
		"file":null,
		"size":64,
		"wait":false,
		"isInit":false
	});
	
	
	if (o) self.setValues(o);
	
	if (!self.wait) self.init();
};

