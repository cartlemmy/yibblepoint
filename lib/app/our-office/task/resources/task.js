sl.task = function(o) {
	var self = this, initState = o.state;
	
	self.isInit = false;
	o.state = "none";
	
	sl.initSlClass(this,"task");
	
	self.init = function() {
		if (initState) self.setState(initState);
		self.isInit = true;
		self.refresh();
		self.refreshT = setInterval(self.refresh,1000);
	};
	
	self.setStartTs = function(ts) {
		self.startTs = ts;
		self.refresh();
	};
	
	self.setDueTs = function(ts) {
		self.dueTs = ts;
		self.refresh();
	};
	
	self.dueText = function() {
		if (self.startTs != 0) return sl.date("today-time",self.startTs);
		if (self.dueTs != 0) return sl.date("today-time",self.dueTs);
		return "en-us|N/A";
	};
	
	self.removeEvent = function(event) {
		self.refresh();
	};
	
	self.updateEvent = function(event) {
		self.refresh();
	};
	
	self.getCurrentEvent = function() {
		return null;
	};
	
	self.setState = function(state) {
		if (self.state == state || self.state == 'remove' || !self.isInit) return;
		self.state = state;
		self.dispatchEvent("state-change",self);
	};
	
	self.setStatus = function(status) {
		status = Number(status);
		if (status >= 2) self.setState("remove");
	};
	
	self.setDELETE = function() {
		self.setState("remove");
	};
	
	self.refresh = function() {
		if (!self.isInit) return;
		if (self.status >= 2) {
			self.setState("remove");
			return;
		}
		
		if (event = self.getCurrentEvent()) {
			return event.type == "timeclock" ? "timeclock" : "active";
		}
		
		var state = "future";
		if (self.startTs && self.startTs <= self.now() && (self.endTs >= self.now() || !self.endTs)) {
			state = "active";
		} else if (self.dueTs == 0 && !self.endTs) {
			state = "todo";
		} else if (self.dueTs != 0 && self.dueTs <= self.now()) {
			state = "overdue";
		} else if (self.dueTs != 0 && self.dueTs < self.today() + 86400) {
			state = "due-today";
		} else if (self.endTs != 0 && self.now() > self.endTs) {
			state = "remove";
		}
		
		self.setState(state);			
	};
	
	self.now = function() {
		var d = new Date();
		return Math.floor(d.getTime() / 1000);
	};
	
	self.today = function() {
		var d = new Date();
		d.setHours(0);
		d.setMinutes(0);
		d.setSeconds(0);
		return Math.floor(d.getTime() / 1000);
	};
	
	self.destruct = function() {
		self.setState("remove");
		clearInterval(self.refreshT);
	};
		
	self.setValues({
		"state":"none",
		"events":[]
	});
	
	self.setValues(o);
};
