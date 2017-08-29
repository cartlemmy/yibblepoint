sl.uploader = function(o) {
	var self = this;
	sl.initSlClass(this,"uploader");
	
	self.init = function() {	
		self.contEl = sl.dg("",self.el,"div",{"className":"uploader"});
		self.fileInputEl = sl.dg("",self.contEl,"input",{"type":"file","multiple":"multiple"});
		self.fileInputEl.addEventListener("change",function(e){
			for (var i = 0; i < e.target.files.length; i++) {
				if (typeAllowed(e.target.files[i].type)) self.upload(e.target.files[i]);
			}
		});
		self.overallProg = new sl.loaderBar(self.contEl,"bytes");
		self.statusEl = sl.dg("",self.contEl,"div",{"className":"status","style":{"marginTop":"15px"}});
	};
	
	self.upload = function(file) {
		self.uploading.push(new sl.uploadFile({"uploader":self,"file":file}));
		self.checkForNextUpload();
	};
	
	self.checkForNextUpload = function() {
		var uploading = false, i;
		for (i = 0; i < self.uploading.length; i++) {
			if (self.uploading[i].uploading) uploading = true;
		}
		
		if (!uploading) {
			for (i = 0; i < self.uploading.length; i++) {
				if (!self.uploading[i].uploaded) {
					self.uploading[i].start();
					return;
				}
			}
			
			for (i = 0; i < self.uploading.length; i++) {
				self.uploading[i].noOverallProgress = true;
			}
		}
	};
	
	self.uploadComplete = function(upload) {
		self.dispatchEvent("uploaded",upload);
		self.checkForNextUpload();
	};
	
	self.updateProgress = function() {
		var pos = 0, tot = 0;
		for (var i = 0; i < self.uploading.length; i++) {
			if (!self.uploading[i].noOverallProgress) {
				pos += self.uploading[i].pos;
				tot += self.uploading[i].file.size;
			}
		}
		self.overallProg.progress(pos,tot);
	};
		
	function typeAllowed(type) {
		for (var i = 0; i < self.allow.length; i++) {
			if (type.search(new RegExp(self.allow[i].split('/').join('\\/').split('*').join('.*'))) != -1) return true;
		}
		return false;
	};
	
	self.setValues({
		"allow":["*"],
		"uploading":[],
		"fields":{}
	});
	
	self.setValues(o);
	self.init();
};

sl.uploadFile = function(o) {
	var self = this;
	sl.initSlClass(this,"upload-file");
	
	var l = 0, icon;
	
	self.setProgress = function(pos) {
		self.pos = pos;
		self.prog.progress(pos,self.file.size);
		self.uploader.updateProgress(pos);
	};
	
	self.init = function() {
		var cont 
		self.el = sl.dg("",self.uploader.statusEl,"div",{"className":"upload-file"});

		var cont = sl.dg("",self.el,"div");
	
		var iconCont = sl.dg("",cont,"div",{"className":"icon"});	
		
		icon = new sl.fileIcon({"el":iconCont,"file":self.file,"wait":true});
		
		self.infoEl = sl.dg("",cont,"div");
		
		sl.dg("",self.infoEl,"label",{"innerHTML":self.file.name});
		
		self.prog = new sl.loaderBar(self.infoEl,"bytes");

	};
	
	self.start = function() {
		if (self.uploading) return;
		
		if ((["image/jpeg","image/png","image/gif"].indexOf(self.file.type)) != -1) {
			sl.require("js/ui/generateThumb.js",function(){
				self.thumb = true;
				sl.generateThumb(self.file,192,function(res){
					icon.init();
					self.dimensions = res.dimensions;
					self.thumb = res.thumb;
					self.dispatchEvent("thumb-loaded");
				});
			});
		}	
		
		self.uploading = true;
		getChunk(0,self.chunkSize,function(chunk){
			self.firstChunkFP = fingerPrint("",chunk);
			process();
		});
	};
	
	self.getFileUID = function() {
		return self.firstChunkFP+"."+self.file.size+"."+self.file.name;
	};
	
	function process() {
		getChunk(self.pos,self.chunkSize,function(chunk,fp){
			self.fingerPrint = fingerPrint(self.fingerprint,chunk);
			sl.coreOb.net.send("file-store",{"pos":self.pos,"uid":self.getFileUID(),"type":self.file.type,"size":self.file.size,"fp":self.fingerPrint,"data":chunk.split(",").pop()},{"queueTime":0},function(res){
				delete chunk;
				if (res && res.success) {
					if (res.md5) { //done
						var completionTimer = null;
						function complete() {
							if (completionTimer) clearTimeout(completionTimer);
							if (self.thumb && self.thumb !== true) self._IMAGE = self.file.name+";"+self.file.type+";"+self.file.size+";"+res.md5+";"+self.dimensions+";"+self.thumb+";"+sl.config.parentUser;
							self.uploader.uploadComplete(self);
						};
						self.setProgress(self.file.size);
						self.uploading = false;
						self.uploaded = true;
						self.prog.destroy();
						if (self.thumb === true) {
							self.addEventListener("thumb-loaded",function() {
								console.log("thumb-loaded");
								complete();
							});
							completionTimer = setTimeout(function(){
								console.log("thumb-failed");
								complete();
							},1000);
						} else complete();
						return;
					} else if (res.partial) {
						//TODO: do we need to check fingerprint?
						self.pos = res.partial.pos;
					} else self.pos += self.chunkSize;
					self.setProgress(self.pos);
					setTimeout(process,10);
				} else {
					//TODO: failed
				}
			});
		});
	};
	
	function fingerPrint(oh,a) {
		var fp = new Uint8Array(32), i, j, h = "";
		for (i = 0; i < fp.length; i++) { fp[i] = 0; }
		
		for (i = 0; i < oh.length; i+=2) {
			fp[(i>>1)&31] = fp[(i>>1)&31] ^ parseInt(oh.substr(i,2), 16);
		}
		
		for (i = 0; i < a.length; i++) {
			fp[i&31] = fp[i&31] ^ a.charCodeAt(i);
		}
		
		for (i = 0; i < fp.length; i++) {
			h += ((fp[i]>>4)&15).toString(16)+(fp[i]&15).toString(16); 
		}
		return h;
	};
	
	function getChunk(pos,len,cb) {
		var reader = new FileReader();
		var blob = self.file.slice(pos, pos + len), fp = false;
		reader.onloadend = function(e) {
			if (e.target.readyState == FileReader.DONE) {
				curPartPos = pos;
				curPart = e.target.result;
				cb(e.target.result);
			}
		};
		reader.readAsDataURL(blob);
	};
	
	self.setValues({
		"file":null,
		"name":{},
		"pos":0,
		"chunkSize":1024*512,
		"uploading":false,
		"fingerprint":"",
		"firstChunkFP":"",
		"uploading":false,
		"uploaded":false,
		"noOverallProgress":false,
		"_IMAGE":false
	});
	
	self.setValues(o);
	self.init();
};
