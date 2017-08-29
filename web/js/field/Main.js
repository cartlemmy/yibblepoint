
window.Field = function(el, type, fc) {
	if (!el) return;
	
	var cT, lv, self = this, elType = el.nodeName+(el.getAttribute('type')?"."+el.getAttribute('type'):'');
	
	self.fieldC = fc;
	self.value = null;
	
	self.val = function(v) {
		var i, g = v === undefined;

		switch (elType) {
			case "SELECT":
				if (g) return el.options[el.selectedIndex].value;
				for (i = 0; i < el.options.length; i++) {
					if (_N(v) == _N(el.options[i].value) || _N(v) == _N(el.options[i].index)) {
						el.selectedIndex = i;
						break;
					}
				}
				break;
				
			default:
				if (g) return el.value;
				el.value = v;
				break;
		}
		self.value = v;
		_change();
	};
	
	self.onChange = function(v){return v};
	
	function _change() {
		var v = self.val();
		if (v != lv) {
			lv = v;
			self.value = self.onChange(v);
		}
	}
	
	function change(e) {
		var wait = (function(){
			switch (e.type) {
				case "change": return 0;
				case "keyup": return 500;
				case "paste": return 50;
			}
		})();
		
		if (cT) clearTimeout(cT);
		if (wait) {
			cT = setTimeout(_change,wait);
		} else {
			_change();
		}
	}
	
	lv = self.val();
	
	el._YIB_FIELD = self;
	el.addEventListener('change',change);
	el.addEventListener('keyup',change);
	el.addEventListener('paste',change);
};
