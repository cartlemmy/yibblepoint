sl.fieldDef.address = {
	"init":function() {
		this.valuedInit = false;
		this.country = sl.config.international.country;
		this.core.loadData("countryList",function(d){});
		this.core.loadData("country?"+this.country,function(d){});
		this.partNames = ["address","type","lat","lng","timezone"];			
		this.adressParts = {};
		return true;
	},
	"postInit":function() {
		var self = this;
		this.typeEl = new sl.dg("",this.el,"select",{"style":{"marginLeft":"8px","width":"80px"}});
		this.typeEl.addEventListener("change",function(){
			self.def.update.call(self,{"type":self.typeEl.options[self.typeEl.selectedIndex].value});
		});

		this.el.parentNode.appendChild(this.typeEl);
		this.el.style.width = ((this.el.offsetWidth - sl.getTotalElementSize(this.el,true).width) - sl.getTotalElementSize(this.typeEl).width)+"px";

		this.el.addEventListener("focus",function(){self.def.showEdit.call(self)});

		var options = {
			"unknown":"en-us|--",
			"home":"en-us|Home",
			"work":"en-us|Work",
			"shipping":"en-us|Shipping",
			"billing":"en-us|Billing",
			"vacation":"en-us|Vacation"
		};
		for (var n in options) {
			sl.dg("",this.typeEl,"option",{
				"value":n,
				"innerHTML":options[n]
			});
		}
	},
	"showEdit":function() {
		var self = this;
		
		if (this.editEl) {
			this.editEl.show();
		} else {
			this.editEl = this.view.floatingElement(this.el);
			this.editEl.clickToDismiss = false;
			
			var cont = this.editEl.contentEl;
			
			var setup = [
				[4,"en-us|Country","250px"],
				[0,"en-us|Address","282px",1],
				[5,"en-us|Address 2","282px",1],
				[1,"en-us|City","100px"],
				[2,"en-us|State / Province","100px"],
				[3,"en-us|Postal Code","50px"]
			];
			
			var fields = [], timer = null, blurTimer = null;
			var sug = [0,3,5];
			
			function setField(field,v) {
				field.value = v;
				change({"type":"change","target":field},true,true);
			};
			
			function change(e,go,noSug) {
				var field = e.target;
				var fieldNum = fields.indexOf(field);
					
				if (go || e.type == "change") {
					if (field.slLastValue === field.value) return;
					field.slLastValue = field.value;
				
					var cd = sl.data.country[self.country];
				
					if (sug.indexOf(fieldNum) != -1 && !field.sug) {
						field.sug = new sl.suggestions({
							"core":self.core,
							"fieldEl":field
						});
						field.sug.addEventListener("select",function(t,o) {
							var cd = sl.data.country[self.country];
							if (o.value == "c") { //Country
								setField(o.field,o.name);
							} else {
								var country = sl.data.countryList.name[sl.data.countryList.iso.indexOf(self.country.toUpperCase())];
								
								if (country) setField(fields[0],country);
								setField(fields[3],cd.city[o.value]);
								setField(fields[4],cd.state[o.value]);
								setField(fields[5],cd.zip[o.value]);
								
								var ll = cd.geocode[o.value].split(";");
								self.def.update.call(self,{"lat":ll[0],"lng":ll[1],"timezone":cd.timezones[cd.timezone[o.value]]});
							}
						});
						self.chainDestruct(field.sug);
					}
					
					var s = field.value.searchify(), len = s.length, match = 0;
					switch (fieldNum) {
						case 0: // find country
							field.sug.clear();
							if (!noSug && len > 1) {
								for (var i = 0; i < sl.data.countryList.iso.length; i++) {
									if (sl.data.countryList.name[i].searchify().substr(0,len) == s) {
										var v = sl.data.countryList.name[i];
										field.sug.add({
											"value":"c",
											"name":v,
											"formatted":sl.highlightMatch(v,field.value)
										});
										match++;
										if (match == 10) break;
									}
								}
							}
							break;
							
						case 3: // find city
							field.sug.clear();
							if (!noSug && len > 1) {
								for (var i = 0; i < cd.city.length; i++) {
									if (cd.city[i].searchify().substr(0,len) == s) {
										var v = cd.city[i]+", "+cd.state[i]+" "+cd.zip[i];
										field.sug.add({
											"value":i,
											"name":v,
											"formatted":sl.highlightMatch(v,field.value)
										});
										match++;
										if (match == 10) break;
									}
								}
							}
							break;
						
						case 5: // find postal code 
							field.sug.clear();
							if (!noSug && len > 3) {
								for (var i = 0; i < cd.city.length; i++) {
									if (cd.zip[i] == s) {
										var v = cd.zip[i]+" ("+cd.city[i]+", "+cd.state[i]+")";
										field.sug.add({
											"value":i,
											"name":v,
											"formatted":sl.highlightMatch(v,field.value)
										});
										match++;
										if (match == 10) break;
									}
								}
							}
							break;
					}
						
					
					var v = ['','','','','',''];
					for (var i = 0; i < fields.length; i++) {
						var tv = fields[i].value.replace(/[\, ]+/g," ");
						var sv = tv.searchify();
						switch (i) {
							case 0: //convert country code
								for (var j = 0; j < sl.data.countryList.iso.length; j++) {
									if (sv == sl.data.countryList.name[j].searchify()) {
										tv = sl.data.countryList.iso[j].toUpperCase();
										break;
									}
								}
								v[setup[i][0]] = tv;
								break;
								
							case 4: //convert state code
								for (var j = 0; j < cd.stateAbbr.abbreviation.length; j++) {
									if (sv == cd.stateAbbr.stateProvince[j].searchify()) {
										tv = cd.stateAbbr.abbreviation[j].toUpperCase();
										break;
									}
								}
								v[setup[i][0]] = tv;
								break;
								
							default:
								v[setup[i][0]] = fields[i].value.replace(/[\, ]+/g," ");
								break;
						}
					}
					while (v.length && v[v.length - 1].trim() == "") { v.pop(); }
					var v = v.join(", ");
					self.el.value = v;
					self.def.update.call(self,{"address":v});
					return;
				}
				if (timer) clearTimeout(timer);
				timer = setTimeout(function(){change(e,true);},100);
			};
			
			var blurred = [true,true];
			function blur(m) {
				blurred[m===1?1:0] = true;
				if (!(blurred[0] && blurred[1])) return;
				if (blurTimer) clearTimeout(blurTimer);
				blurTimer = setTimeout(function(){
					self.def.hideEdit.call(self);
				},100);
			};
			
			function focus(m) {
				blurred[m===1?1:0] = false;
				if (blurTimer) clearTimeout(blurTimer);
			};
			
			cont.addEventListener("mouseover",function(){focus(1)});
			cont.addEventListener("mouseout",function(){blur(1)});
			
			var del = sl.dg("",cont,"div",{
				"style":{
					"float":"left",
					"margin":"15px 2px 0 2px",			
				},
				"title":"en-us|Delete"
			});
			self.core.setCommonIcon(del,"delete");
			del.addEventListener("click",function(){
				if (self.listener) self.listener.dispatchEvent("change",{"field":self.n,"value":""});
			});
			
			var cd = sl.data.country[self.country];
			var i, startV = self.el.value.split(",");
			for (i = 0; i < setup.length; i++) {
				var s = setup[i];
				(function(i,s){
					var c = sl.dg("",cont,"fieldset",{"className":"variable"});
					sl.dg("",c,"label",{"innerHTML":s[1]});
					var field = sl.dg("",c,"input",{"type":"text","style":{"width":s[2]}});
					field.addEventListener("change",change);
					field.addEventListener("keyup",change);
					field.addEventListener("focus",focus);
					field.addEventListener("blur",blur);
					fields.push(field);
					if (startV[setup[i][0]]) {
						var v = startV[setup[i][0]].trim();
						var sv = v.searchify();
						switch (i) {
							case 0:
								for (var j = 0; j < sl.data.countryList.iso.length; j++) {
									if (sv == sl.data.countryList.iso[j].searchify()) {
										v = sl.data.countryList.name[j];
										break;
									}
								}
								break;
								
							case 4:
								if (cd) {
									for (var j = 0; j < cd.stateAbbr.abbreviation.length; j++) {
										if (sv == cd.stateAbbr.abbreviation[j].searchify()) {
											v = cd.stateAbbr.stateProvince[j];
											break;
										}
									}
								}
								break;
						}
						field.value = v;
					}
				})(i,s);
			}
			self.focusField = fields[0];	
		}
		self.focusField.focus();
		
	},
	"hideEdit":function() {
		this.editEl.hide();
	},
	"setValue":function(value) {
		if (this.valuedInit) return;
		this.valuedInit = true;
		
		this.adressParts = sl.delimToObject(value,this.partNames);
		
		this.def.update.call(this,this.adressParts,true);
	},
	"setType":function(type) {
		for (var i = 0; i < this.typeEl.options.length; i++) {
			if (this.typeEl.options[i].value == type) {
				this.typeEl.selectedIndex = i;
				break;
			}
		}
	},
	"update":function(o,init) {
		var self = this;
		if (o === undefined) o = {};
		var uv = [];
		for (var i = 0; i < this.partNames.length; i++) {
			var n = this.partNames[i];
			if (o[n] !== undefined) {
				this.adressParts[n] = o[n];
				switch (n) {
					case "type":
						this.def.setType.call(this,o[n]);
						break;
				}
			}
		}

		var v = sl.objectToDelim(this.adressParts,this.partNames);
		this.setValue(v);
		
		if (self.listener && !init) {
			if (this.blurTimer) clearTimeout(this.blurTimer);
			this.blurTimer = setTimeout(function(){
				self.listener.dispatchEvent("blur",{"field":self.n,"value":self.value.value,"changed":true});
			},100);
		}
	},
	"change":function(text) {
		this.def.update.call(this);
	}
};

