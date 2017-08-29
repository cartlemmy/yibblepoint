self.notifications = {};
self.chatQueue = {};

sl.require("js/media/sound.js",function(){
	self.listen("*",function(type,o){
		var sounds;
		if (sl.config.dev.debug && sl.config.dev.logDispatcher) self.log(type+" ",o);
		if (!o) return;
		
		if (1/*TODO: pass to activity stream?*/) {
			//console.log(type,o);
		}
		
		if (type == "alert") {
			if (o.sound) {
				if (sounds = o.sound.split("\n")) {
					function playSound() {
						var sound = sounds.shift();
						sl.sound.play(sound,function(){
							if (sounds.length) playSound();
						});
					}					
					playSound();
				}
			} else {
				alert(o.title+"\n\n"+o.message);
			}
		}
			
		var answer = false, id;
		if (type.substr(0,7) == "answer-") {
			answer = true;
			type = type.substr(7);
		}
		
		if (type == "user-activity" && o.type == "force-logout") { //TODO: validate
			self.core.logout();
			return;
		}
		
		if (o.id) {
			id = o.id;
		} else {
			id = type+(o.user?"-"+o.user:"");
			o.id = id;
		}
		
		if (answer) {
			removeNotificationById(id,o.message);
			return;
		}
		
		if (self.notifications[id]) return;
			
		var n = notify(type,o);
		if (n) {
			self.notifications[o.id] = n;
		} else {
			delete o.id;
		}
	});
});

function removeNotificationById(id,message) {
	if (self.notifications[id] && typeof(self.notifications[id]) != "string") {
		if (message) {
			var message = new sl.messageBox({"element":self.notifications[id].el,"message":message.split("\n").join("<br>"),"core":self.core,"timer":4});
		}
		self.core.removeNotification(self.notifications[id]);
		self.notifications[id] = null;
	}
};

function notify(type,o) {
	switch (type) {
		case "remote-chat-request":
			return self.core.addNotification({
				"name":"en-us|Chat Request",
				"type":type,
				"id":o.id,
				"message":sl.format("en-us|Chat Requested\nFrom %%\nClick to answer",o.name),
				"answerMessage":"en-us|Chat request answered",
				"onclick":function(){
					self.core.net.sendEvent(o.user,"chat-answered",{"user":sl.config.user,"name":sl.config.name});
					self.core.open("chat/?"+o.user);
				},
				"icon":"important",
				"persistent":true,
				"requiresAnswer":true,
				"globalAnswer":true
			});

		case "remote-chat-disconnect":
			removeNotificationById("remote-chat-request-"+o.user,"en-us|Requester has disconnected");
			return;
		
		case "chat-message":
			if (!self.core.isOpen("chat")) {
				if (!self.chatQueue[o.from]) self.chatQueue[o.from] = [];
				self.chatQueue[o.from].push(o.message);
				
				o.id = "chat-message-"+o.from;
				return self.core.addNotification({
					"name":"en-us|Chat",
					"type":type,
					"id":o.id,
					"message":o.message+"\n-"+o.from,
					"onclick":function(){
						removeNotificationById(o.id);
						var app = self.core.open("chat/?"+o.from);
						
						app.addEventListener("loaded",function() {
							app.appendChat(o.from,self.chatQueue[o.from]);
							self.chatQueue[o.from] = [];
						});
					},
					"icon":"important"
				});
			}
			return;
			
		default:
			//console.log(type,o);
			return;
	}
};

self.listen("user-*",function(type,v){
	if (!v) return;
	switch (type.substr(5)) {
		case "credits":
			sl.config.credits = v;
			break;
	}
});
