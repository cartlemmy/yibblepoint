sl.fieldDef.multiselect = {
	"init":function() {
		var self = this;
		
		self.optionEls = {};
		for (var n in self.options) {
			var v = self.options[n];
			if (v != "!NOEDIT") {
				var id = "f-"+self.view.handle+"-"+self.n+"-"+n;
				var cont = sl.dg("",self.contEl,"div",{"className":"cb-cont"});
				var el = self.optionEls[n] = sl.dg(id,cont,"input",{"type":"checkbox"});
				if (self.force && self.force[n]) {
					el.disabled = true;
					el.checked = self.force[n][0];
				}
				(function(el,n){
					el.addEventListener("change",function(){
						if (self.force && self.force[n] && self.force[n][1]) {
							alert(self.force[n][1]);
						}
						self.def.update.call(self);
					},false);

					if (self.force && self.force[n] && self.force[n][1]) {
						el.title = self.force[n][1];
					}

				})(el,n);
				sl.dg("",cont,"label",{"for":id,"innerHTML":v});
			}
		}
		return false;
	},
	"update":function() {
		var value = this.value.value.split(",");
		for (var n in this.optionEls) {
			if (this.optionEls[n].checked && value.indexOf(n) == -1) {
				value.push(n);
			}
			if (!this.optionEls[n].checked && value.indexOf(n) != -1) {
				value.splice(value.indexOf(n),1);
			}
		}
		this.changed(value.join(","),true);
	},
	"setValue":function(value) {
		value = value.split(",");
		for (var n in this.options) {
			if (this.optionEls[n]) this.optionEls[n].checked = value.indexOf(n) != -1;
		}
		return true;
	}
};
