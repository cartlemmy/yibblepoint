sl.fieldDef.bitMask = {
	"init":function() {
		var self = this;
		
		self.optionEls = [];
		for (var n = 0; n < self.options.length; n++) {
			var v = self.options[n];
			if (v != "!NOEDIT") {
				var id = "f-"+self.view.handle+"-"+self.n+"-"+n;
				var cont = sl.dg("",self.contEl,"div",{"className":"cb-cont"});
				var el = sl.dg(id,cont,"input",{"type":"checkbox"});
				self.optionEls.push(el);
				
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
		var value = Number(this.value.value);
		for (var n = 0; n < this.optionEls.length; n++) {
			value = value & (Math.pow(2,n)^0x7FFFFFFF);
			if (this.optionEls[n].checked) value = value | Math.pow(2,n);
		}
		this.changed(value);
	},
	"setValue":function(value) {
		value = Number(value);
		for (var n = 0; n < this.options.length; n++) {
			if (this.optionEls[n]) this.optionEls[n].checked = !!(Math.pow(2,n) & value);
		}
		return true;
	}
};
