function channelView(parent) {
	var self = this;
	self.channels = [];
	self.requestingUpdate = false;
	self.parent = parent;
	
	self.refresh = function() {
		if (self.requestingUpdate) return;
		self.requestingUpdate = true;
		core.request("get-channels",{},function(newChannels){
			//console.log(newChannels);
			self.requestingUpdate = false;
			
			//Remove channels
			for (var i = 0; i < self.channels.length; i++) {
				if (self.getChannelById(self.channels[i].id, newChannels) == null) self.remove(self.channels[i].id);
			}
			
			//Add / update channels
			for (var i = 0; i < newChannels.length; i++) {
				if (self.getChannelById(newChannels[i].id) == null) {
					self.add(newChannels[i].id, newChannels[i]);
				} else {
					self.update(newChannels[i].id, newChannels[i]);
				}
			}
			
			self.sort();		
		});
	};
	
	self.sorting = false;
	self.sort = function() {
		if (self.sorting) return;
		self.sorting = true;
		for (var i = 0; i < self.channels.length - 1; i++) {
			if (self.channels[i].modified < self.channels[i + 1].modified) {
				self.channels[i].offset = {"target":$(self.channels[i].el.cont).topHeight(),"pos":0};
				self.channels[i + 1].offset = {"target":0-$(self.channels[i].el.cont).topHeight(),"pos":0};
				swap(i);
				return;
			}
		}
		self.sorting = false;
	};
	
	function swap(num) {
		var swapping = 0;
		for (var i = num; i < num + 2; i++) {
			var channel = self.channels[i];
			var ch = (channel.offset.target - channel.offset.pos) / 2;
			if (ch > 0) ch = Math.min(4,ch);
			if (ch < 0) ch = Math.max(-4,ch);
			channel.offset.pos += ch;
			
			channel.el.cont.style.position = "relative";
			channel.el.cont.style.top = Math.round(channel.offset.pos)+"px";
			if (Math.abs(channel.offset.target - channel.offset.pos) > 1) swapping ++;
		}
		if (swapping) {
			setTimeout(function(){swap(num);},50);
		} else {
			var ch1 = self.channels[num], ch2 = self.channels[num + 1];
			ch1.el.cont.style.top = ch2.el.cont.style.top = "0px";
			
			var el = ch2.el.cont.parentNode.removeChild(ch2.el.cont);
			ch1.el.cont.parentNode.insertBefore(el,ch1.el.cont);
			
			self.channels[num] = ch2;
			self.channels[num + 1] = ch1;
			 
			self.sorting = false;
			self.sort();
		}
	};
	
	self.add = function(id, data) {
		data.el = {};
		data.el.cont = dg("",self.parent,"div",{"className":"channel","id":id});
		data.el.name = dg("",data.el.cont,"label");
		data.el.userCount = dg("",data.el.cont,"div",{"className":"user-count"});
		data.el.noteCount = dg("",data.el.cont,"div",{"className":"note-count"});
		data.el.extraIcons = dg("",data.el.cont,"div",{"className":"ch-extra-icons"});
		data.el.modified = dg("",data.el.cont,"div",{"className":"modified"});
		data.el.lastText = dg("",data.el.cont,"div",{"className":"last-text"});
		
		dg("",data.el.cont,"div",{"style":{"clear":"both"}});
		
		data.el.cont.addEventListener("click",function(){
			core.go('channel?id='+id);
		});
		self.channels.push(data);
		
		self.update(id,data);
		
		//console.log(data);
	};
	
	self.update = function(id, data) {
		var channel = self.getChannelById(id);
		
		var extraIcons = [];
		for (var n in data) {
			switch (n) {
				case "creator":
					if (data.creator == core.publicUserKey) extraIcons.push(["star","Creator","#AC0004"]);
					break;
					
				case "name":
					channel.el.name.innerHTML = data.name;
					break;
					
				case "modified":
					channel.el.modified.innerHTML = date("n/j/Y @ g:ia", data.modified);
					break;
					
				case "lastText":
					channel.el.lastText.innerHTML = data.lastText;
					break;
			}
			if (n != "el") channel[n] = data[n];
		}

		$(channel.el.extraIcons).html(function() {
			var rv = [];
			for (var n in extraIcons) {
				var ee = extraIcons[n];
				rv.push('<i class="icon-'+ee[0]+'" style="color:'+ee[2]+'" title="'+ee[1]+'"></i>');
			}
			return rv.join(" ");
		});
		
		channel.el.userCount.innerHTML = '<i class="icon-user"></i> '+channel.filteredUsers.length;
		
		var count = 0;
		for (var i in channel.notes) {
			count ++;
		}
		channel.el.noteCount.innerHTML = '<i class="icon-list-alt"></i> '+count;
	};

	self.remove = function(id) {
		var channel = self.getChannelById(id);
		$(channel.el.cont).fadeOut(400,function(){
			channel.el.cont.parentNode.removeChild(channel.el.cont);
		});
		self.channels.splice(self.getChannelById(id,false,true),1);
	};
	
	self.getChannelById = function(id,channelList,retID) {
		if (!channelList) channelList = self.channels;
		for (var i = 0; i < channelList.length; i++) {
			if (channelList[i].id == id) return retID ? i : channelList[i];
		}
		return retID ? -1 : null;
	};
	
	self.refresh();
	setInterval(self.refresh,10000);
};
