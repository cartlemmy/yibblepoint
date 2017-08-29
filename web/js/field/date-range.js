window.FieldDateRange = function(el) {
	var self = this, sEl, eEl, popover, closeWhenDone = false;
	self.field = new Field(el, 'date-range', self);
	
	
	self.field.onChange = function(v) {
		var i, p, none = true;
		if (v.toLowerCase() != "none") {
			v = v.split(/\s*(to|\-)\s*/);
			
			for (i = 0; i < v.length; i+=2) {
				p = $('#yibui-date-range-picker .'+(i == 0 ? 'start' : 'end'));
				p.val(v[i]);
				if (v[i]) none = false;
			}
			
			if (v.length < 3) $('#yibui-date-range-picker .end').val('');
		}
		
		$('#date-range-pick-'+(none?"none":"pick"))[0].checked = true;
	}
	
	function pick() {
		$('#date-range-pick-pick')[0].checked = true;
		
		closeWhenDone = $('#yibui-date-range-picker .end').val() == '';
	}
	
	function refresh() {
		if ($('#date-range-pick-pick')[0].checked) {
			self.field.val(sEl._YIB_FIELD.val()+(eEl._YIB_FIELD.val()?' to '+eEl._YIB_FIELD.val():''),true);
		} else {
			self.field.val('None',true);
		}
	}
	
	self.show = function() {
		if (popover && !popover._ACTIVE) {
			$('#yibui-date-range-picker')[0]._CUR_PICKER = null;
			self.shown = false;
		}
		
		if (self.shown) return;
		
		var init = !!$('#yibui-date-range-picker').length, pel = $('#yibui-date-range-picker')[0];
		
		if (pel && pel._CUR_PICKER) {
			if (pel._CUR_PICKER == self) return;
			pel._CUR_PICKER.hide(self.show);
			return;
		}
		
		popover = yibPopover('yibui-date-range-picker',el);
		pel = $('#yibui-date-range-picker')[0];
		
		pel._CUR_PICKER = self;
		self.shown = true;
		
		if (!init) {
			$(popover).append(
				'<div class="field stacked">'+
					'<label>Date Range</label>'+
					'<div class="cb">'+
						'<label><input name="date-range-pick" id="date-range-pick-none" value="none" type="radio" CHECKED>None</label>'+
					'</div>'+
					'<div class="cb">'+
						'<label><input name="date-range-pick" id="date-range-pick-pick" value="pick" type="radio">Pick</label>'+
					'</div>'+
				'</div>'+
				'<div class="fc">'+
					'<div class="field">'+
						'<label>Start</label>'+
						'<input type="text" class="yibfield date start">'+
					'</div>'+
					'<div class="field">'+
						'<label>End</label>'+
						'<input type="text" class="yibfield date end">'+
					'</div>'+
					'<div class="clearfix"></div>'+
				'</div>'
			);
					
			window.yibFieldScan();
			sEl = $('#yibui-date-range-picker .start')[0];
			eEl = $('#yibui-date-range-picker .end')[0];
			

			$(sEl).focus(pick);
			sEl._YIB_FIELD.onChange = function(v){
				if ($('#yibui-date-range-picker')[0]._CUR_PICKER != self) return;
				refresh();
			};
			
			$(eEl).focus(pick);
			eEl._YIB_FIELD.onChange = function(v){
				if ($('#yibui-date-range-picker')[0]._CUR_PICKER != self) return;
				refresh();
				if (closeWhenDone && eEl._YIB_FIELD.fieldC.date) self.hide();
			};
			
			$('#date-range-pick-none').click(refresh);
			$('#date-range-pick-pick').click(refresh);
			
		}

		popover.yibuiFixPos();		
	};
	
	self.hide = function(cb) {
		if (!self.shown) return;
		self.shown = false;
		$('#yibui-date-range-picker')[0]._CUR_PICKER = false;
		$('#yibui-date-range-picker').stop().fadeOut(cb);
	};
	
	function fb(e) {
		if (e.type == 'focus') {
			self.show();
		} else {
			//self.hide();
		}
	}
	
	el.addEventListener('focus',fb);
	el.addEventListener('blur',fb);
};
