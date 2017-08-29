
window.FieldDate = function(el) {
	if (!el) return;
	
	var self = this, 
		popover = false,
		months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
	
	self.field = new Field(el, 'date', self);
	self.format = ['m','d','y'];
	
	self.shown = false;
	self.date = null;
	self.curCal = null;
	
	self.field.onChange = function(v) {
		self.setDate(v);
	}
	
	self.setFromTS = function(ts) {
		self.date = new Date();
		self.date.setTime(Number(ts));
		self.refresh();
		var t = [];
		for (i = 0; i < 3; i++) {
			switch (self.format[i]) {
				case 'm':
					t.push(self.date.getMonth() + 1);
					break;
				
				case 'd':
					t.push(self.date.getDate());
					break;
					
				case 'y':
					t.push(self.date.getFullYear());
					break;
			}
		}
		self.field.val(t.join('/'));
	}
	
	self.setDate = function(v) {
		var i;
		
		v = v.split(/[^\d]+/);
		
		if (v.length == 3) {
			self.date = new Date();
			for (i = 0; i < 3; i++) {
				switch (self.format[i]) {
					case 'm':
						self.date.setMonth(Number(v[i])-1);
						break;
					
					case 'd':
						self.date.setDate(Number(v[i]));
						break;
						
					case 'y':
						self.date.setFullYear(Number(v[i]));
						break;
				}
			}
		} else {
			self.date = null;
		}
		
		self.refresh();
	}
	
	function nav(m) {
		self.curCal.setMonth(self.curCal.getMonth() + m);
		self.refresh();
	}
	
	self.refresh = function(v) {
		if (!self.curCal) self.curCal = self.date ? self.date : new Date();
		
		var r, c, cell, fd = firstDOM(self.curCal), fdow = fd.getDay(),
			cm = self.curCal.getMonth(),
			cs, d = new Date(fd.getFullYear(), fd.getMonth(), 1 - fdow),
			rows = $('#yibui-date-picker .cal tr');
		
		$('#yibui-date-picker .cur').html(months[self.curCal.getMonth()]+' '+self.curCal.getFullYear());
		
		for (r = 0; r < 6; r ++) {
			for (c = 0; c < 7; c ++) {
				cell = rows[r].childNodes[c];
				cell.innerHTML = d.getDate();
				cell._TS = d.getTime();
				
				cs = [];
				if (cm == d.getMonth()) {
					cs.push('m');
					if (sameDay(self.curCal,d)) cs.push('today');
					if (sameDay(self.date,d)) cs.push('sel');
				}
				
				cell.className = cs.join(' ');				
				
				d.setDate(d.getDate() + 1);
			}
		}
	};
	
	function firstDOM(d) {
		return new Date(d.getFullYear(), d.getMonth(), 1);
	}
	
	function sameDay(d1,d2) {
		if (!d1 || !d2) return false;
		return d1.getFullYear() == d2.getFullYear() && d1.getMonth() == d2.getMonth() && d1.getDate() == d2.getDate();
	}
	
	self.show = function() {
		if (popover && !popover._ACTIVE) {
			self.shown = false;
			$('#yibui-date-picker')[0]._CUR_PICKER = null;
		}
		
		if (self.shown) return;
		
		var init = !!$('#yibui-date-picker').length, pel = $('#yibui-date-picker')[0];
		
		if (pel && pel._CUR_PICKER) {
			if (pel._CUR_PICKER == self) return;
			pel._CUR_PICKER.hide(self.show);
			return;
		}
				
		popover = yibPopover('yibui-date-picker',el);
		pel = $('#yibui-date-picker')[0];
		
		pel._CUR_PICKER = self;
		self.shown = true;
		
		if (!init) {
			$(popover).append(
				'<table>'+
					'<thead>'+
						'<tr class="nav"><th class="prev-y">&lt;&lt;</th><th class="prev-m">&lt;</th><th colspan="3" class="cur"></th><th class="next-m">&gt;</th><th class="next-y">&gt;&gt;</th></tr>'+
					'</thead>'+
					'<tbody class="sp"><tr><td colspan="7"></td></tr></tbody>'+
					'<thead>'+
						'<tr class="l"><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr>'+
					'</thead>'+
					'<tbody class="cal">'+(function(){
						var r, c, t = '';
						for (r = 0; r < 6; r ++) {
							t += '<tr>';
							for (c = 0; c < 7; c ++) {
								t += '<td></td>';
							}
							t += '</tr>';
						}
						return t;
					})()+'</tbody>'+
				'</table>'
			);
			self.setDate(self.field.val());
		}

		$('#yibui-date-picker .prev-y').click(function(){if ($('#yibui-date-picker')[0]._CUR_PICKER != self) return;nav(-12)});
		$('#yibui-date-picker .prev-m').click(function(){if ($('#yibui-date-picker')[0]._CUR_PICKER != self) return;nav(-1)});
		$('#yibui-date-picker .next-m').click(function(){if ($('#yibui-date-picker')[0]._CUR_PICKER != self) return;nav(1)});
		$('#yibui-date-picker .next-y').click(function(){if ($('#yibui-date-picker')[0]._CUR_PICKER != self) return;nav(12)});
		$('#yibui-date-picker .cal td').click(function(e){
			if ($('#yibui-date-picker')[0]._CUR_PICKER != self) return;
			self.setFromTS(e.delegateTarget._TS);
			self.hide();
		});
		popover.yibuiFixPos();		
	};
	
	self.hide = function(cb) {
		if (!self.shown) return;
		self.shown = false;
		$('#yibui-date-picker')[0]._CUR_PICKER = false;
		$('#yibui-date-picker').stop().fadeOut(cb);
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
