sl.fieldDef.date = {
	"init":function() {
		return true;
	},
	"postInit":function() {
		var self = this;
		if (!self.el.parentNode) return;
		
		self.dp = false;
		
		if (!self.noIcon && !self.dateRange) {
			sl.require("js/ui/datePicker.js");
			var icon = sl.dg("",self.el.parentNode,"div");
			self.core.setCommonIcon(icon,"date-pick");
			icon.addEventListener("click",function(){
				if (!sl.datePicker) return;
				if (self.dp) {
					self.dp.toggle();
				} else {
					self.dp = new sl.datePicker({"el":self.el,"date":self.value.value});
					self.dp.show();
				}
			});
			self.defWidth = self.el.offsetWidth - sl.getTotalElementSize(self.el,true).width - 24;
			self.el.style.width = (self.defWidth - sl.getTotalElementSize(icon).width)+"px";
		} else {
			sl.require("js/ui/datePicker.js",function(o){
				self.dp = new sl.datePicker({"el":self.el,"date":self.value.value,"nullLabel":"Any"});
				if (self.siblingField) {
					if (self.siblingField.dp) {
						self.dp.sibling = self.siblingField.dp;
						self.siblingField.dp.sibling = self.dp;
					}
					self.dp.addEventListener("shown",function(){
						self.siblingField.dp.hide();
					});
				}
			});
		}
	}
};

