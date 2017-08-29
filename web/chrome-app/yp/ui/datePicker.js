sl.datePicker = function(o) {
	var self = this, contEl, blurTimer;
	sl.initSlClass(this,"date-picker");

	self.init = function() {
		var tr, thead, tbody, td, a, i, el;
		contEl = sl.dg("",self.el.parentNode,"div",{"className":"date-picker"});
		self.divEl = sl.dg("",contEl,"div",{});
		
		self.tableEl = sl.dg("",self.divEl,"table");
		
		thead = sl.dg("",self.tableEl,"thead");
		
		tr = sl.dg("",thead,"tr");
		
		var nav = [
			["prev-year","&lt;&lt;",-12],
			["prev-month","&lt;",-1],
			["ym-label",false,0],
			["next-month","&gt;",1],
			["next-year","&gt;&gt;",12]
		];
		
		
		
		for (i = 0; i < nav.length; i++) {
			if (nav[i][1]) {
				td = sl.dg("",tr,"th",{"style":{"textAlign":i<3?"left":"right","cursor":"pointer"}});
				el = sl.dg("",td,"a",{"innerHTML":nav[i][1]});
				(function (months){
					td.addEventListener("click",function(){self.navigate(months)});
				})(nav[i][2]);
				self.nav[nav[i][0]] = td;
			} else {
				self.ymEl = sl.dg("",tr,"th",{"colSpan":3,"style":{"textAlign":"center"}});
			}
		}
		
		
		tbody = sl.dg("",self.tableEl,"tbody");
		tr = sl.dg("",tbody,"tr");
		for (i = 0; i < 7; i++) {
			sl.dg("",tr,"td",{"className":"dow","innerHTML":self.dow[i].charAt(0),"style":{"width":"14.2857%"}});			
		}
		
		for (i = 0; i < 6; i++) {
			tr = sl.dg("",tbody,"tr");
			for (j = 0; j < 7; j++) {
				var el = sl.dg("",tr,"td");
				el.addEventListener("click",pick);
				self.cells.push(el);
			}
		}
		
		self.el.addEventListener("focus",self.show);
		self.el.addEventListener("blur",function(){
			blurTimer = setTimeout(self.hide,200);
		});
		self.hide();
		
		tr = sl.dg("",tbody,"tr");
		td = sl.dg("",tr,"td",{"className":"dow","colSpan":7});	
		a = sl.dg("",td,"a",{"href":"javascript:;","innerHTML":self.nullLabel});
		a.addEventListener("click",function(){
			self.el.value = self.nullLabel;
			self.pickedDate = 0;
			if (self.sibling) self.sibling.setPickedOtherDate(self.pickedDate);
			if (self.el.slSpecial) self.el.slSpecial.changed(self.nullLabel,true);
			self.dispatchEvent("picked",0);
			self.hide();
		});
		self.initialized = true;
	};
	
	function pick() {
		self.el.value = this.slPickDate;
		self.pickedDate = self.dateToDay(new Date(this.slPickDateTS));
		self.setDate(this.slPickDateTS/1000);
		if (self.el.slSpecial) self.el.slSpecial.changed(self.el.value,true);
		self.dispatchEvent("picked",this.slPickDateTS/1000);
		self.hide();
	};
	
	self.toggle = function() {
		self.visible = !self.visible;
		self.dispatchEvent(self.visible?"shown":"hidden");
		self.updateVisibility();
	};
	
	self.show = function() {
		if (!self.visible) self.dispatchEvent("shown");
		self.visible = true;
		self.updateVisibility();
	};
	
	self.hide = function() {
		if (self.visible) self.dispatchEvent("hidden");
		self.visible = false;
		self.updateVisibility();
	};
	
	self.setDate = function(d,fromNav) {
		if (typeof(d) == "number" && d != 0) {
			self.date = new Date(d*1000);
		} else if (typeof(d) == "object" && d instanceof Date) {
			self.date = d;
		}
		if (!fromNav) {
			self.pickedDate = self.dateToDay(self.date);
			if (self.sibling) self.sibling.setPickedOtherDate(self.pickedDate);
		}
	};
	
	self.setPickedOtherDate = function(d) {
		self.pickedOtherDate = d;
		self.update();
	};
	
	self.updateVisibility = function() {
		self.divEl.style.display = self.visible ? "" : "none";
		//self.el.style.backgroundColor = self.visible ? "#FF0" : "";
		if (self.visible) self.update();
	};
	
	self.update = function() {
		if (!self.initialized) return;
		self.ymEl.innerHTML = self.months[self.date.getMonth()]+" "+self.date.getFullYear();
				
		var i;
		var day = new Date(self.date.getFullYear(), self.date.getMonth(), 1, 0, 0, 0, 0);
		var thisMonth = self.date.getMonth();
		var pStart = 0, pEnd = 0;
		
		if (self.pickedOtherDate && self.pickedDate) {
			pStart = Math.min(self.pickedOtherDate,self.pickedDate);
			pEnd = Math.max(self.pickedOtherDate,self.pickedDate);
		}
		day.setDate(day.getDate() - day.getDay(), true);
		
		
		i = 0;
		for (var row = 0; row < 6; row++) {
			for (col = 0; col < 7; col++) {
				var d = self.dateToDay(day);
				self.cells[i].innerHTML = day.getDate();
				self.cells[i].slPickDate = sl.date ? sl.date(sl.config.international.date,day.getTime()/1000) : (day.getMonth()+1)+"/"+day.getDate()+"/"+day.getFullYear();
				self.cells[i].slPickDateTS = day.getTime();
				if (self.pickedDate == self.dateToDay(day)) {
					self.cells[i].className = "day selected";
				} else {
					self.cells[i].className =
						"day"+
						(day.getMonth()!=thisMonth ? " other-month":"")+
						(d >= pStart && d <= pEnd ? " today":"")+
						(self.dateToDay() == self.dateToDay(day) ? " range":"");
				}
				day.setDate(day.getDate()+1);
				i++;
			}
		}
	};
	
	self.dateToDay = function(d) {
		if (d === undefined) d = new Date();
		if (!d) return 0;
		if (typeof(d) == "numeric") d = new Date(d * 1000);
		return d.getFullYear() * 372 + d.getMonth() * 31 + (d.getDate() - 1);
	}
	
	self.navigate = function(months) {
		setTimeout(function(){if (blurTimer) clearTimeout(blurTimer);},50);
		self.date.setDate(1);
		self.date.setMonth(self.date.getMonth() + months);
		self.update();
	};
	
	self.setValues({
		"el":null,
		"nav":{},
		"cells":[],
		"dow":[
			"en-us|Sunday","en-us|Monday","en-us|Tuesday",
			"en-us|Wednesday","en-us|Thursday","en-us|Friday",
			"en-us|Saturday"
		],
		"months":[
			"en-us|January","en-us|February","en-us|March",
			"en-us|April","en-us|May","en-us|June","en-us|July",
			"en-us|August","en-us|September","en-us|October",
			"en-us|November","en-us|December"
		],
		"date":(new Date()),
		"pickedDate":0,
		"nullLabel":"en-us|None",
		"pickedOtherDate":0,
		"visible":false,
		"initialized":false
	});
	
	if (o) self.setValues(o);
	
	self.init();
}

