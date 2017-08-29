function userStatus() {
	var self = this;
	
	self.users = [];
		
	self.requestingUpdate = false;
	self.parent = $("#users")[0];
	
	self.refresh = function() {
		if (self.requestingUpdate) return;
		self.requestingUpdate = true;
		
		core.request("get-users",{},function(newUsers){
			self.requestingUpdate = false;
			
			//Remove users
			for (var i = 0; i < self.users.length; i++) {
				if (self.getUserById(self.users[i].id, newUsers) == null) self.remove(self.users[i].id);
			}
			
			//Add / update users
			for (var i = 0; i < newUsers.length; i++) {
				if (self.getUserById(newUsers[i].id) == null) {
					self.add(newUsers[i].id, newUsers[i]);
				} else {
					self.update(newUsers[i].id, newUsers[i]);
				}
			}
			
		});
	};
	
	self.add = function(id, data) {
		data.el = {};
		data.el.tr = dg("",self.parent,"tr",{"id":id,"style":{"cursor":"pointer"}});
		data.el.tr.addEventListener("click",function(){
			if (data.pos) {
				$("#map")[0].src = "http://maps.google.com/maps?q="+data.pos.lat+","+data.pos.lng+"&output=embed";
			}
		});
		data.el.name = dg("",data.el.tr,"td");
		data.el.active = dg("",data.el.tr,"td");
		data.el.pos = dg("",data.el.tr,"td");

		self.users.push(data);
		
		self.update(id,data);
		
		//console.log(data);
	};
	
	function fmt(v) {
		return v.toPrecision(7);
	};
	
	self.update = function(id, data) {
		var user = self.getUserById(id);
		
		var extraIcons = [];
		for (var n in data) {
			switch (n) {
				case "name":
					user.el.name.innerHTML = data.name;
					break;
					
				case "active":
					user.el.active.innerHTML = date("n/j/Y g:ia",data.active);
					break;
					
				case "pos":
					var t = data.pos ? fmt(data.pos.lat)+", "+fmt(data.pos.lng) : "N/A";
					
					user.el.pos.innerHTML = data.pos.ts > core.unixTS() - config.MAX_POS_AGE ? t : '<i style="color:#999">' + t + ' (old)</i>';
					break;
			}
			if (n != "el") user[n] = data[n];
		}
	};

	self.remove = function(id) {
		var user = self.getUserById(id);
		$(user.el.cont).fadeOut(400,function(){
			user.el.cont.parentNode.removeChild(user.el.cont);
		});
		self.users.splice(self.getUserById(id,false,true),1);
	};
	
	self.getUserById = function(id,userList,retID) {
		if (!userList) userList = self.users;
		for (var i = 0; i < userList.length; i++) {
			if (userList[i].id == id) return retID ? i : userList[i];
		}
		return retID ? -1 : null;
	};
	
	self.refresh();
	setInterval(self.refresh,10000);
};

var us = new userStatus();
