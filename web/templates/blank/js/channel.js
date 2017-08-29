function channel(id) {
	var self = this;
	var NONE = 0, HORIZ = 1, VERT = 2;
	
	self.id = id;
	self.notes = [];
	self.requestingUpdate = false;
	self.parent = $('#notes')[0];
	self.el = {};
	
	self.init = function() {
		self.el.input = dg('message');
		self.el.input.addEventListener("keypress",function(e){
			if (e.keyCode == 13) self.send();
		});
		
		self.el.send = dg('send');
		self.el.send.addEventListener("click",self.send);
		
		self.atBottom = true;
		self.scrollToBottom();
		
		$('#notes')[0].addEventListener("scroll",self.positionCheck);
		
		if ($('#message-button')[0]) {
			$('#message-button').click(function(){	
				self.showMessageCompose($('#below-notes')[0].style.height == "0px");
			});
		}
		
		$("#attach-image")[0].addEventListener("click",function(){
			if (self.sending) return;
			$("#imageSelect")[0].click();
		});
		
		
		$("#remove-attachments")[0].addEventListener("click",function(){
			if (self.sending) return;
			self.removeAttachments();
			$("#attachments")[0].style.display = "none";
		});
		
		$('#imageSelect')[0].addEventListener("change",function(e) {
			if (self.sending) return;
			var files = e.target.files, file;
			if (files && files.length > 0) {
				file = files[0];
				$("#attachments")[0].style.display = "";
				self.attach("image",file);
			}
		});
		
		$("#full-image-cont")[0].addEventListener("click",function(){
			hideImage();
		});
		
		window.addEventListener("resize",updateImageSize);		
	};
	
	self.showMessageCompose = function(show) {
		if (!$('#message-button-cont')[0]) return;
		var bn = $('#below-notes')[0];
		bn.style.height = show ? "" : "0px";
		bn.style.overflow = show ? "" : "hidden";
		if (show) self.el.input.focus();
		if (self.resizeCallback) self.resizeCallback();
	};
				
	self.removeAttachments = function() {
		attachments = {};
		$("#attachments")[0].style.display = "none";
	};
	
	self.attach = function(type,file) {
		var exif = null;
		var fileReader = new FileReader();

		fileReader.onload = function(fileLoadedEvent) {
			function imageLoaded() {
				var canvas = document.createElement('canvas');
				var w = Math.round(160 * (image.width / image.height)), h = 160;
				var origW = w, origH = h;
				
				var orientation = {"angle":0,"flip":NONE};
				
				if (exif && exif.Orientation) {
					orientation = (function(){
						var orMap = [
							[0,NONE], // Invalid value
							[0,NONE],
							[0,HORIZ],
							[2,NONE],
							[0,VERT],
							[1,HORIZ],
							[1,NONE],
							[-1,HORIZ],
							[-1,NONE]
						];
						
						var o = orMap[exif.Orientation];
						return o ? {"angle":o[0] * Math.PI / 2,"flip":o[1]} : false;
					})();
					
					if (orientation) { //Rotate according to EXIF
						if (Math.sin(orientation.angle) > 0.9) {
							var w = Math.round(160 * (image.height / image.width)), h = 160;
							origW = h; origH = w;
						}	
					}
				}
				
				canvas.style.height = "80px";			
				canvas.style.cssFloat = "left";
				canvas.setAttribute('width', w);
				canvas.setAttribute('height', h);
				var ctx = canvas.getContext('2d');
				
				
				ctx.save();
				
				//TODO: flip
					
				if (orientation.angle) {
					ctx.translate(w / 2,h / 2);
					ctx.rotate(orientation.angle);
				}
				
				orientation.angle = Math.round(orientation.angle / (Math.PI / 2));
				attachments.image.orientation = orientation;
				
				ctx.drawImage(
					image,
					0,0,image.width,image.height,
					- origW / 2,- origH / 2,origW,origH
				);

				ctx.restore();
				
				$("#attachments-box")[0].appendChild(canvas);
				
				attachments[type].thumb = canvas.toDataURL();
			};
			
			
			attachments[type] = {"thumb":null,"full":null};
			imageFull = fileLoadedEvent.target.result;
					
			switch (type) {
				case "image":
					exif = EXIF.readFromBinaryFile(new BinaryFile(atob(fileLoadedEvent.target.result.substr(0,1000).split(",").pop().substr(0,768))));
					var image = new Image();
					image.src = fileLoadedEvent.target.result;
					if (image.complete) {
						imageLoaded();
					} else {
						image.onload = imageLoaded;
					}
					break;
			}
		};

		fileReader.readAsDataURL(file);
	};
	
	function encodeDataUrl(data) {
		if (config.SECURE_STORAGE != "TRUEVAULT") return data;
		data = data.split(",",2);
		data[0] += "\n";
		while (data[0].length % 3 != 0) {
			data[0] += "\n";
		}
		return btoa(data[0])+data[1];
	};
	
	function decodeDataUrl(data) {
		if (config.SECURE_STORAGE != "TRUEVAULT") return data;
		var head = atob(data.substr(0,90)).match(/^[^\n]+\n+/);
		return head[0].split(/\n+/,2).shift()+","+data.substr(head[0].length * (3/2));
	};
	
	self.apply = function(newNotes) {
		//Remove notes
		for (var i = 0; i < self.notes.length; i++) {
			if (self.getNoteById(self.notes[i].id, newNotes) == null) self.remove(self.notes[i].id);
		}
		
		//Add / update notes
		for (var i = 0; i < newNotes.length; i++) {
			if (self.getNoteById(newNotes[i].id) == null) {
				self.add(newNotes[i].id, newNotes[i]);
			} else {
				self.update(newNotes[i].id, newNotes[i]);
			}
		}
		self.scrollToBottom();
	};
	
	var imageFull = null, audioFull = null;
	var attachments = {"audio":null,"image":null};

	var sendQueue = [];
	self.send = function(e) {
		var text = $(self.el.input).val().trim();
		
		if (!attachments.audio && !attachments.image && !text) return;
		
		if (self.setSending(true)) return;
		
		if (e) { cancelBubble(e); preventDefault(e); }
		var ts = core.unixTS();
		
		
		tv.setDocumentPermission({"channel":self.id});
		tv.post({"text":text,"audio":attachments.audio,"image":attachments.image},function(res){
			//console.log(res);
			if (res && res.success) {
				var remains = 1;
				function complete() {
					remains --;
					if (!remains) {
						imageFull = attachments.audio = attachments.image = null;
						self.removeAttachments();
						self.setSending(false);
						self.showMessageCompose(false);
					}
				};
				
				if (res) {
					res.ts = ts;
					
					if (imageFull) {
						remains++;
						tv.post(encodeDataUrl(imageFull),function(imgRes){
							if (imgRes) {
								attachments.image.full = imgRes.document_id;
								tv.put(res.document_id,{"text":text,"audio":attachments.audio,"image":attachments.image},function(putRes){
									core.request("update-note",{"id":self.id,"noteId":res.document_id,"update":{"image":true}},function(res){
										//console.log(res);
									});
									complete();
								});
							} else {
								complete();
							}
						},true);
					}
				
					sendQueue.push(res);
					trySend();
					complete();
					
				} else {
					//Data Storage issue
				}
			}
		});
		
		$(self.el.input).val("");
		return false;
	};
	
	var sendTimer = null;
	function trySend() {
		if (self.requestingUpdate) {
			if (sendTimer) clearTimeout(sendTimer);
			sendTimer = setTimeout(trySend,100);
			return;
		}
		self.requestingUpdate = true;
		core.request("get-notes",{"id":self.id,"send":sendQueue},function(newNotes){
			self.requestingUpdate = false;
			self.apply(newNotes);
		});
		while (sendQueue.pop()) {}
	};
	
	self.setSending = function(sending) {
		if (self.sending == sending) return true;
		self.sending = sending;
		self.el.send.disabled = sending;
		false;
	};
	
	self.userNames = {};
	self.getUserName = function(id,cb){
		if (id == core.publicUserKey) {
			cb("You");
			return;
		}
		if (self.userNames[id]) {
			cb(self.userNames[id]); 
			return;
		}
		core.request("get-user-name",{"id":id},function(name){
			cb(name); 
		});
	};
	
	self.refresh = function() {
		if (self.requestingUpdate) return;
		self.requestingUpdate = true;
		core.request("get-notes",{"id":self.id},function(newNotes){
			self.requestingUpdate = false;
			self.apply(newNotes);
		});
	};
	
	self.add = function(id, data) {
		data.el = {};
		data.el.outCont = dg("",self.parent,"div",{"className":"note","id":id});
		data.el.cont =  dg("",data.el.outCont,"div",{});
		data.el.name = dg("",data.el.cont,"div",{"className":"name"});
		data.el.ts = dg("",data.el.cont,"div",{"className":"ts"});
		data.el.text = dg("",data.el.cont,"div",{"className":"text"});
		
		dg("",data.el.cont,"div",{"style":{"clear":"both"}});
		
		self.notes.push(data);
		
		self.update(id,data,true);
		
		self.scrollToBottom();
	};
	
	self.positionCheck = function() {
		self.atBottom = $('#notes').scrollTop() >= $('#notes')[0].scrollHeight - $('#notes').height();
	};
	
	self.scrollToBottom = function() {
		if (self.atBottom) $('#notes').scrollTop($('#notes')[0].scrollHeight);
	};

	function updateImageSize(e) {
		var w, h, x, y, im = $('#full-image');
		
		var imRatio = im[0].naturalWidth / im[0].naturalHeight;
				
		if (im[0].flipXY) imRatio = 1 / imRatio;

		var cw = $("#full-image-cont").width(), ch = $("#full-image-cont").height();
		var contRatio = cw / ch;
		
		if (imRatio > contRatio) {
			w = cw;
			h = cw / imRatio;
			x = im[0].flipXY ? Math.round((w - h) / 2) : 0;
			y = Math.round((ch - h) / 2);
		} else {
			w = ch * imRatio;
			h = ch;
			y = im[0].flipXY ? Math.round((h - w) / 2) : 0;
			x = Math.round((cw - w) / 2);
		}
		
		im[0].style.left = x+"px";
		im[0].style.top = y+"px";
		
		if (im[0].flipXY) {
			im.width(h);
			im.height(w);
		} else {
			im.width(w);
			im.height(h);
		}
		
		setTimeout(function(){
			if (!(e && e.type == "resize")) $("#full-image-loading").hide();
		},100);
	};
	
	function showImage(id,info) {
		
		var im = $('#full-image')[0], fil = $("#full-image-loading"), fic = $("#full-image-cont");
		fil.show();
		fic.show();
		
		fil.offset({
			"left":Math.round((fic.width() - fil.width()) / 2),
			"top":Math.round((fic.height() - fil.height()) / 2)
		});
				
		im.src = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";
		tv.get(id,function(o) {
				
			im.src = decodeDataUrl(o);
			
			if (!info.orientation) info.orientation = {"angle":0,"flip":NONE};
			
			im.flipXY = Math.sin(info.orientation.angle * Math.PI / 2) > 0.9;
			
			setBleedingEdgeStyle(im,"transform","rotate("+(info.orientation.angle * 90)+"deg)");
			
			if (im.complete) {
				updateImageSize();
			} else {
				im.onload = updateImageSize;
			}
		},true);
	};
	
	function hideImage() {
		$("#full-image-cont").hide();
	};
	
	self.update = function(id, data, added) {
		if (!data) return;
		var channel = self.getNoteById(id);
		
		function attachmentUpdate() {
			if (channel.image && channel.el.thumb.style.cursor != "pointer") {
				channel.el.thumb.style.cursor = "pointer";
				channel.el.thumb.addEventListener("click",function(){
					showImage(channel.message.image.full,channel.message.image);
				});
			}
		};
		
		function updateNote() {
			tv.get(channel.id,function(o) {
				if (o && typeof(o) == "object") {
					channel.el.text.innerHTML = o.text;
					channel.message = o;
					
					if (o.image) {
						channel.el.thumb = dg("",channel.el.text,"img",{"src":o.image.thumb,"style":{"height":"80px"}});
					}
					
					//TODO: handle audio
					
					dg("",channel.el.text,"div",{"style":{"clear":"both"}});
					attachmentUpdate();
				} else {
					channel.loading = false;
					// True vault error
				}
			});
		};
				
		var update = false;
		for (var n in data) {
			if (added || channel[n] != data[n]) {
				switch (n) {
					case "id":
						update = true;
						break;
						
					case "creator":
						data.el.outCont.className = "note "+(data.creator == core.publicUserKey ? "you" : "them");
						self.getUserName(data.creator,function(name){
							data.el.name.innerHTML = name;
						});
						break;
					
					case "image":
						update = true;
						break;
						
					case "ts":
						data.el.ts.innerHTML = date("n/j/Y @ g:ia", data.ts);
						break;
				}
			}
			if (n != "el") channel[n] = data[n];
		}
		if (update) updateNote();
	};

	self.remove = function(id) {
		
	};
	
	self.getNoteById = function(id,noteList) {
		if (!noteList) noteList = self.notes;
		for (var i = 0; i < noteList.length; i++) {
			if (noteList[i].id == id) return noteList[i];
		}
		return null;
	};
	
	self.refresh();
	setInterval(self.refresh,2000);
	
	self.init();
};
