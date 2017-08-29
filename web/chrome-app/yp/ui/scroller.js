sl.scroller = function(o) {

	var self = this;
	sl.initSlClass(this,"scroller");

	self.addColumn = function(id,o) {
		var i;
		for (i = 0; i < self.el.childNodes.length; i++) {
			if (self.el.childNodes[i].nodeType == 1) {
				var el = sl.dg("",self.el.childNodes[i],"div",{});
				el.setAttribute("data-slid",id);
				extra = {};
				o.col = self.colExtra.length;
				for (var n in o) {
					if (n != "label" && n != "updateJS") {
						if (typeof(o[n]) == "string" || typeof(o[n]) == "number") {
							el.setAttribute("data-value-"+n,o[n]);
						} else if (typeof(o[n]) == "boolean") {
							if (o[n]) el.setAttribute("data-value-"+n,"1");
						} else {
							extra[n] = o[n];
						}
					}
				}
				
				if (self.headEl) {
					var head = sl.dg("",self.headEl.childNodes[0],"div",{"innerHTML":(o.label ? o.label : id),"style":{"position":"relative"}});
					head.setAttribute("data-slid",id);
					if (o.headIcons && o.headIcons.length) {
						extra.iconCont = sl.dg("",head,"div",{"style":{"height":"18px","position":"absolute","right":"0","top":"0","margin":"2px 4px 0 0"}});
						for (i = 0; i < o.headIcons.length; i++) {
							var icon = o.headIcons[i];
							(function(icon){
								var iconEl = sl.dg("",extra.iconCont,"div",{"style":{"width":"18px","height":"18px","cssFloat":"left"}});
								
								icon.setSrc = function(src) {
									icon.src = src;
									iconEl.style.backgroundImage = "url('"+src+"')";
								};
								icon.setSrc(icon.src);
								
								iconEl.addEventListener("click",function(e){
									icon.click(icon,iconEl);
									sl.preventDefault(e);
									sl.cancelBubble(e);
								});
							})(icon);
						}
					}
				}
		
				self.colExtra.push(extra);
				break;
			}
		}
		if (o.total) self.totalRow = true;
	};
	
	self.parentNode = function() {
		return self.el.parentNode;
	};
	
	self.init = function() {
		if (self.isInit) return;

		if (self.itemEl) {
			self.itemSize = sl.getTotalElementSize(self.itemEl);
			
			if (!self.itemSize.width || !self.itemSize.height) {
				setTimeout(self.init,250);
				return;
			}
		}
				
		self.el.slSpecial = self;
		
		//Find scroller-head
		var c = sl.getChildNodes(self.parentNode());
		
		for (var i = 0; i < c.length; i++) {
			if (c[i].className == "scroller-head") {
				self.headEl = c[i];
				self.headScrollEl = self.headEl.childNodes[0];
				self.headElCol = [];
				self.headWidth = [];
				self.headColDesc = false;
				self.lastHeadClick = null;
				
				if (self.checkableRows) self.headWidth.push(0);
				
				var ch = self.headScrollEl.childNodes, col = 0;
				for (var j = 0; j < ch.length; j++) {
					if (ch[j].nodeName == "DIV") {
						var head = ch[j];
						self.headWidth.push(sl.getTotalElementSize(head).width + 16);  //Nope

						if (self.clickableHead) {
							if (self.orderby) {
								var id = head.getAttribute("data-slid");
								if (!id) id = head.innerHTML.toCamelCase();
								if (id == self.orderby) {
									head.className = "asc";
									self.lastHeadClick = head;
									if (self.colExtra[col].iconCont) self.colExtra[col].iconCont.style.marginRight = "20px";
								}
							}
							
							head.style.cursor = "pointer";
							(function(col){
								sl.addEventListener(head,"click",function(e){
									var id = this.getAttribute("data-slid");
									if (!id) id = this.innerHTML.toCamelCase();
									
									if (self.sortOrderHead) {
										self.headColDesc = self.lastHeadClick != this ? false : !self.headColDesc;
										if (self.lastHeadClick) self.lastHeadClick.className = "";
										this.className = (self.headColDesc ? "desc" : "asc");
									}
									
									//self.colExtra[col]
									self.lastHeadClick = this;

									for (var k = 0; k < self.colExtra.length; k++) {
										if (self.colExtra[k].iconCont) self.colExtra[k].iconCont.style.marginRight = k == col ? "20px" : "";
									}
									
									self.dispatchEvent("head-click",{"id":id,"order":self.headColDesc ? "desc" : "asc","e":e});
								},false);
							})(col);
						}
						self.headElCol.push(head);
						col++;
					}					
				}
				break;	
			}		
		}
				
		if (self.checkableRows) {
			self.headElCheckboxAnch = sl.dg("",self.el.parentNode,"div",{"style":{"position":"absolute"}},{"before":self.el.parentNode.childNodes[0]});
					
			self.headElCheckbox = sl.dg("",self.headScrollEl,"div",{
				"style":{
					"width":"12px",
					"cursor":"pointer",
					"position":"relative"
				},
				"innerHTML":"&check;"
			},{"before":self.headElCol[0]});
					
			self.headElCol.unshift(self.headElCheckbox);
			
			var headCheckboxMenu = new sl.menu({"core":self.view.core,"buttonEl":self.headElCheckbox,"anchorEl":self.headElCheckboxAnch,"contents":self.checkboxMenu,"align":"horizontal","offY":24});
		
			sl.addEventListener(headCheckboxMenu,"click",function(type,o) {
				self.dispatchEvent("checkbox-menu",o.item.action,o.item,o.event);
			});
		}
		
		self.itemEl = null;
		
		for (var i = 0; i < self.el.childNodes.length; i++) {
			if (self.el.childNodes[i].nodeType == 1) {
				var el = self.el.childNodes[i];
				if (el.className == "no-items") {
					self.noItems = true;
					self.noItemsOverlayEl = el;
					self.noItemsMessage = el.innerHTML;
				}
				if (el.className == "scroller-row") {					
					self.itemEl = el;
					self.itemElClass = el.className;
							
					self.scrollerTable = true;
					var c = el.childNodes;
					self.tableColWidth = [];
					if (self.checkableRows) self.tableColWidth.push(0);
					
					if (!self.iconInit) {
						self.iconInit = true;
						var iconCont = sl.dg("",self.itemEl,"div",{"className":"icon"},{"before":c[0]});
					}
					
					for (var j = 1; j < c.length; j++) {
						if (c[j].nodeName == "DIV" && c[j].className != "icon") {
							self.tableColWidth.push(0);
						}
					}
														
					self.itemSize = sl.getTotalElementSize(self.itemEl);
				}
			}
		}
				
		if (!self.itemSize.width || !self.itemSize.height) {
			setTimeout(self.init,250);
			return;
		}
		
		if (!self.itemEl || !self.tableColWidth.length) return;
				
		sl.removeChildNodes(self.el,[self.noItemsOverlayEl,self.bottomEl]);
		
		self.sectionContEl = sl.dg("",self.el,"div",{
			"className":"scroller-sections",
			"style":{
				"opacity":0,
				"display":"none",
				"position":"absolute"
			}
		});
		
		sl.addEventListener(self.el,"mousemove",function(e) {
			if (e.offsetX <= self.el.offsetWidth && e.offsetX >= self.el.offsetWidth - sl.scrollBarWidth) self.showSections(true);
		},false);
		
		sl.addEventListener(self.sectionContEl,"mouseover",function() {
			self.sectionOver = true;
		},false);
		
		sl.addEventListener(self.sectionContEl,"mouseout",function() {
			self.sectionOver = false;
		},false);
		
		self.bottomEl = sl.dg("",self.el,"div",{
			"style":{
				"left":"0px",
				"width":"1px",
				"height":"1px",
				"position":"absolute"
			}
		});
				
		sl.addEventListener(self.el,"scroll",self.refresh,false);
	
		self.elPad = sl.getTotalElementSize(self.el,true);
		
		self.view.addEventListener("resize",self.refreshSize);
		
		self.showSections(true);
		self.isInit = true;
		self.refresh();
		
		self.setItemCount(self.itemCount);
		
		if (self.totalRow) {
			self.totalRow = new sl.scrollerItem({"total":true,"scroller":self,"width":self.itemSize.width,"height":self.itemSize.height});
			self.refreshTotalRow(self.totalRow.setTotalRow);
		}
		
		if (self.indirectSize) self.refreshSize();
	};

	self.refreshTotalRow = function() {};
	
	self.refreshSize = function(type,viewSize) {
		self.viewSize = viewSize;
		var c = self.el.parentNode.childNodes;

		var otherHeight = -self.elPad.height;
		for (var i = 0; i < c.length; i++) {
			if (c[i] != self.el && c[i].nodeType == 1 && c[i].className != "loader-overlay") {
				var size = sl.getTotalElementSize(c[i]);
				otherHeight += size.height;
			}
		}
		
		if (self.totalRow) self.totalRow.updatePosition();
		
		if (self.indirectSize) {
			self.el.style.width = self.el.style.height = "1px";
			var inner = sl.innerSize(self.el.parentNode);
			self.viewSize = {
				"width":inner.width,
				"height":inner.height
			};			
		}
		
		self.el.style.width = (self.viewSize.width - self.elPad.width) + "px";
		self.el.style.height = (self.viewSize.height - otherHeight) + "px";

		self.showSections(true);
		self.refresh();
		if (self.noItems) sl.centerInParent(self.noItemsOverlayEl);
	};
	
	var refreshTimer = null;
	self.refresh = function() {
		if (!self.isInit || !self.itemEl || !self.tableColWidth.length) return;
				
		switch (self.viewMode) {
			case "icon":
				if (self.viewSize) {
					self.iconCols = Math.floor(self.viewSize.width / self.iconSize);
					self.rowHeight = self.iconSize / self.iconCols;
					self.height = Math.ceil(self.rowHeight * self.itemCount);
				} else {
					self.iconCols = 1;
					self.rowHeight = self.iconSize;
					self.height = (self.rowHeight * self.itemCount);
				}
				break;
			
			default:
				self.iconCols = 1;
				self.rowHeight = self.itemSize.height;
				self.height = (self.rowHeight * self.itemCount);
				break;
		}
		
		
		self.bottomEl.style.top = (self.height + (self.totalRow && self.totalRow !== true ? sl.getTotalElementSize(self.totalRow.el).height : 0) - 1)+"px";
		
		if (self.scrollY != self.el.scrollTop) self.showSections(true);
		
		self.scrollX = self.el.scrollLeft;
		self.scrollY = self.el.scrollTop;
		
		if (self.totalRow && self.totalRow !== true) self.totalRow.updatePosition();
		
		//Update sections
		self.sectionContEl.style.top = self.scrollY+"px";
		if (self.sectionsOb.length) {
			var sectionContWidth = 0, sectionPad = sl.getTotalElementSize(self.sectionsOb[0].el,true).width;
			
			for (var i = 0; i < self.sectionsOb.length; i++) {
				self.sectionsOb[i].el.style.width = "";
				sectionContWidth = Math.max(sectionContWidth,self.sectionsOb[i].el.scrollWidth + sectionPad);
			}
			
			for (var i = 0; i < self.sectionsOb.length; i++) {
				self.sectionsOb[i].el.style.width = (sectionContWidth - sectionPad)+"px";
			}
			
			self.sectionContEl.style.width = sectionContWidth + "px";
			self.sectionContEl.style.left = (self.scrollX + self.el.clientWidth - sl.getTotalElementSize(self.sectionContEl).width)+"px";
			self.sectionContEl.style.height = self.el.clientHeight+"px";
		}
		
		if (self.headScrollEl) self.headScrollEl.style.left = (0 - self.scrollX)+"px";
			
		var scrollItem = self.el.scrollTop / self.rowHeight;
		
		self.firstItemInView = Math.floor(scrollItem);
		self.lastItemInView = Math.min(self.itemCount - 1,Math.ceil(scrollItem + (self.el.offsetHeight / (self.itemSize.height / self.iconCols))));
		
		self.firstItem = Math.max(0,Math.floor(scrollItem) - (self.itemPreload * self.iconCols));
		self.lastItem = Math.min(self.itemCount - 1,Math.ceil(scrollItem + (self.el.offsetHeight / (self.itemSize.height / self.iconCols))) + (self.itemPreload * self.iconCols));

		if (refreshTimer) clearTimeout(refreshTimer);
		refreshTimer = setTimeout(function() {
			var i;
			
			if (self.el.clientHeight != self.lastElClientHeight && self.sectionsOb.length) {
				for (var i = 0; i < self.sectionsOb.length; i++) {
					self.sectionsOb[i].refresh();
				}
				self.lastElClientHeight = self.el.clientHeight;
			}
		
			//Check for scroller items that are out of view and deactivate them
			for (i = 0; i < self.loadedItems.length; i++) {
				self.loadedItems[i].deactivateIfOutOfView();
			}
			
			for (i = self.firstItem; i <= self.lastItem; i++) {
				if (self.getItemByIndex(i) == -1) {
					var liIndex = self.getNewScrollerItem(i);
					self.loadedItems[liIndex].setItemIndex(i);
				}
			}
			
		},50);
	};
	
	self.requestObjectCount = function(){};
	
	self.reload = function(noObCnt) {
		function go() {
			if (self.checked) self.checked.setAll(0);
			var i, section;
			
			while (section = self.sectionsOb.pop()) {
				section.destruct();
			}
					
			for (i = 0; i < self.loadedItems.length; i++) {
				self.loadedItems[i].setDeactivated(true);
				self.loadedItems[i].itemIndex = -1;
			}
			self.requestSections();
			self.refresh();
		};
		
		if (noObCnt === true) {
			go();
		} else {
			self.requestObjectCount(function() {
				go();
			});
		}
	}
	
	self.itemLoaded = function(item) {
		self.allLoaded = self.loadedItems.length > 0;
		
		for (var i = 0; i < self.loadedItems.length; i++) {
			if (!self.loadedItems[i].deactivated && !self.loadedItems[i].loaded) {
				self.allLoaded = false;
			}
		}
			
		if (self.allLoaded && !self.firstAllLoaded) {
			self.firstAllLoaded = true;
			self.initialSizing();
		}
	};

	self.isChecked = function(item) {
		var i = self.getItemIndex(item);
		if (i != -1) {
			return self.checked.isBitSet(item.itemIndex);
		}
		return false;
	};
	
	self.getCheckedAsBase64 = function() {
		return self.checked.getAsBase64();
	};
	
	self.getCheckedAsMostEfficient = function() {
		return self.checked.getAsMostEffecient();
	};
	
	self.setChecked = function(item,v,fromClick) {
		var i = self.getItemIndex(item);
		if (i != -1) {
			self.checked.setBit(item.itemIndex,v);
			if (!fromClick && (item = self.getItemAtIndex(i))) {
				item.checkCellBox.checked = v;
			}
		}		
	};
	
	self.checkAll = function(v) {
		self.checked.setAll(!!v || v === undefined);
		for (i = 0; i < self.loadedItems.length; i++) {
			self.loadedItems[i].checkCellBox.checked = v;
		}
	};
	
	self.checkAllInView = function(v) {
		for (i = 0; i < self.loadedItems.length; i++) {
			if (self.loadedItems[i].isInView()) self.setChecked(self.loadedItems[i],v);
		}
	};
	
	self.getItemIndex = function(item) {
		if (typeof(item) == "number") return item;
		if (item && item.itemIndex != -1) return item.itemIndex;
		return -1;
	};
	
	self.getItemAtIndex = function(item) {
		for (i = 0; i < self.loadedItems.length; i++) {
			if (self.loadedItems[i].itemIndex == item) {
				return self.loadedItems[i];
			}
		}
		return null;
	};
		
	self.initialSizing = function() {
		if (self.indirectSize) return;
		
		var w = 0;
		for (var i = 0; i < self.tableColWidth.length; i++) {
			w += self.tableColWidth[i];
		}
		self.view.setSize(w,null);
	};
	
	self.getItemByIndex = function(liIndex) {
		for (var i = 0; i < self.loadedItems.length; i++) {
			if (self.loadedItems[i].itemIndex == liIndex) return i;
		}
		return -1;
	};
	
	self.getNewScrollerItem = function(itemIndex) {
		for (i = 0; i < self.loadedItems.length; i++) {
			if (self.loadedItems[i].deactivated) {
				return i;
			}
		}
		self.loadedItems.push(new sl.scrollerItem({"scroller":self,"width":self.itemSize.width,"height":self.itemSize.height}));
		return self.loadedItems.length - 1;
	};
	
	self.addBottomElement = function() {
		if (!self.bottomEl) self.bottomEl = sl.dg("",self.el.parentNode,"div",{"className":"scroller-bottom","style":{"zIndex":10}});
		return self.bottomEl;
	};
	
	self.setItemCount = function(count) {
		self.itemCount = count;
		
		if (!self.isInit) return;
		
		if (count) self.requestSections();
		
		if (!self.checked) {
			self.checked = new sl.bitArray(count);
		} else {
			self.checked.setLength(count);
		}
		
		self.refresh();
		
		if (!self.noItemsOverlayEl) return;
		
		var noItems = (count == 0);

		if (self.noItems != noItems) {
			if (self.noItemsOverlayEl) {
				var el = self.noItemsOverlayEl;
				el.style.display = noItems ? "block" : "none";
				if (noItems) sl.centerInParent(self.noItemsOverlayEl);
			}
			self.noItems = noItems;
		}
	};
	
	var sectionFaderTimer = null;
	self.sectionFader = function() {
		if (self.sectionOver) {
			self.sectionsOpacity = 2;
		} else {
			self.sectionsOpacity -= 0.1;
		}
		if (self.sectionsOpacity > 0) {
			self.sectionContEl.style.opacity = Math.min(1,self.sectionsOpacity);
		} else {
			self.sectionContEl.style.opacity = 0;
			self.sectionContEl.style.display = "none";
			clearInterval(sectionFaderTimer);
		}
	};
	
	self.showSections = function(show) {
		if (self.noSections || self.itemCount < 100) return;
		if (sectionFaderTimer) clearInterval(sectionFaderTimer);
		if (show) {
			self.sectionContEl.style.display = "";
			self.sectionContEl.style.opacity = 1;
		}
		self.sectionsOpacity = 5.0;		
		self.showSectionsValue = show;

		sectionFaderTimer = setInterval(self.sectionFader, 50);
	};
	
	self.setSections = function(sections) {		
		if (sections.length) self.noSections = false;
		self.showSections(true);
		
		if (sections.length) {
			for (var i = 0; i < sections.length; i++) {
				self.addSection(sections[i]);
			}
			for (var i = 0; i < self.sectionsOb.length; i++) {
				self.sectionsOb[i].refresh();
			}		
		} else self.noSections = true;
		self.refresh();
	};
	
	self.addSection = function(section) {
		self.noSections = false;
		self.sectionsOb.push(new sl.scrollerSection({"scroller":self,"index":section[0],"name":section[1]}));
		self.sectionsOb.sort(function(a,b){
			return a.index - b.index;
		});
	};
	
	self.nextSectionIndex = function(section) {
		var i = self.sectionsOb.indexOf(section);
		if (i == -1) return 0;
		i++;
		if (i < self.sectionsOb.length) return self.sectionsOb[i].index;
		return self.itemCount;
	}
	
	self.getRelativeSection = function(section,p) {
		var i = self.sectionsOb.indexOf(section);
		if (i == -1) return null;
		i += p;
		if (i >=0 && i < self.sectionsOb.length) return self.sectionsOb[i];
		return null;
	};
	
	self.scrollToItem = function(item) {
		self.el.scrollTop = Math.min(self.el.scrollHeight,self.itemSize.height * item);
		self.refresh();
	};
	
	self.declareCellSize = function(row,col,size) {
		var oldWidth = self.tableColWidth[col];
		self.tableColWidth[col] = Math.min(self.maxTableCellWidth,Math.max(self.tableColWidth[col],size.width,self.headWidth[col]));
				
		if (oldWidth != self.tableColWidth[col]) {
			var pad;
			for (var j = 0; j < self.loadedItems.length; j++) {
				self.loadedItems[j].fixWidth();
			}
			if (self.totalRow) self.totalRow.fixWidth();
			for (var i = 0; i < self.tableColWidth.length; i++) {
				if (self.headEl) {
					pad = sl.getTotalElementSize(self.headElCol[i],true);
					self.headElCol[i].style.width = (self.tableColWidth[i]-pad.width)+"px";
				}
			}
		}
	};
	
	self.setNoItemsMessage = function(message) {
		self.noItemsOverlayEl.innerHTML = message ? message : self.noItemsMessage;
	};
	
	self.setViewMode = function(viewMode) {
		self.viewMode = viewMode;
	};
	
	self.requestItem = function(itemIndex) {
		
	};
	
	self.requestSections = function() {
		
	};
	
	self.destruct = function() {

	};
	
	self.setValues({
		"scrollerTable":false,
		"itemEl":null,
		"colExtra":[],
		"noItems":false,
		"noItemsOverlayEl":null,
		"itemCount":0,
		"itemSize":null,
		"loadedItems":[],
		"allLoaded":false,
		"firstAllLoaded":false,
		"clickableHead":false,
		"clickableRows":false,
		"scrollX":0,
		"scrollY":0,
		"height":0,
		"itemPreload":2,
		"firstItem":0,
		"lastItem":0,
		"maxTableCellWidth":240,
		"tableColWidth":[],
		"headWidth":[],
		"sectionsOpacity":1,
		"sectionsOb":[],
		"noSections":false,
		"checked":null,
		"indirectSize":false,
		"bottomEl":null,
		"isInit":false,
		"totalRow":false,
		"viewMode":"list",
		"iconSize":196
	});
	
	if (o) self.setValues(o);
	
	self.init();
};

sl.scrollerItem = function(o) {
	var self = this;
	sl.initSlClass(this,"scroller-item");
	
	self.init = function() {
		self.el = self.scroller.itemEl.cloneNode(true);
		
		if (self.total) self.el.className = self.el.className+" total";
		
		if (self.scroller.checkableRows) {
			var checkCell = sl.dg("",self.el,"div",{"style":{"width":"12px"}},{"before":self.el.childNodes[0]});
			
			if (self.scroller.viewMode == "icon") {
				checkCell.style.position = "absolute";	
				checkCell.style.zIndex = 2;	
				checkCell.style.top = "0";	
			}
			
			checkCell.style.cursor = "default";
			
			if (!self.total) {
				sl.addEventListener(checkCell,"click",function(e){
					if (e.target != self.checkCellBox) {
						self.scroller.setChecked(self,!self.checkCellBox.checked);
						sl.cancelBubble(e);
					}
				},true);
				
				self.checkCellBox = sl.dg("",checkCell,"input",{"type":"checkbox"});
				
				sl.addEventListener(self.checkCellBox,"click",function(e){
					self.scroller.setChecked(self,this.checked,1);
					sl.cancelBubble(e);
					self.cbCheck = true;
				},true);
			}
		}
		
		var c = sl.getChildNodes(self.el);
		for (var i = 0; i < c.length; i++) {	
			if (c[i].getAttribute) {
				if (c[i].className == "icon") {
					self.iconEl = c[i];
				} else {
					var valueType = c[i].getAttribute("data-value-type") ? c[i].getAttribute("data-value-type") : "text";
					
					if (valueType != "text" || c[i].getAttribute("data-value-multi")) {
						var attr = c[i].attributes;
						var o = {"type":valueType};
						for (var j = 0; j < attr.length; j++) {
							var n = attr.item(j).nodeName;
							if (n.substr(0,11) == "data-value-") {
								o[n.substr(11)] = c[i].getAttribute(n);
							}
						}
						
						var col = Number(o.col);
						if (!isNaN(col)) {
							for (var n in self.scroller.colExtra[col]) {
								o[n] = self.scroller.colExtra[col][n];
							}
						}
					
						o.el = c[i];
						c[i].slValue = new sl.value(o);
					}
				}
			}
		}		
		
		if (self.scroller.clickableRows && !self.total) {
			self.el.style.cursor = "pointer";
			sl.addEventListener(self.el,"click",function(e){
				self.scroller.dispatchEvent("click",{"item":self,"event":e});
			},false);
		}
		
		self.el.style.position = "absolute";
		self.scroller.el.appendChild(self.el);
		
		if (self.scroller.scrollerTable) {
			var c = self.el.childNodes;
			for (var i = 0; i < c.length; i++) {
				if (c[i].nodeName == "DIV" && c[i].className != "icon") {
					self.cols.push(c[i]);				
				}
			}
		}
		
	};
	
	self.setItemIndex = function(itemIndex) {
		self.iconSrc = "";
		
		self.setDeactivated(false);
		self.setAsLoaded(false);
		
		if (self.scroller && self.itemIndex != itemIndex) {
			self.scroller.requestItem.call(self,itemIndex);
		}
		self.itemIndex = itemIndex;
		self.refresh();
	};
		
	self.loadingMessage = function(elements) {
		self.loadingMessageElements = [];
		
		if (self.iconEl) sl.removeChildNodes(self.iconEl);
		
		for (var i = 0; i < elements.length; i++) {
			var element = self.element(elements[i]);
			if (element) {
				element.style.opacity = 0.5;
				element.style.fontStyle = "italic";
				element.innerHTML = "";
				self.loadingMessageElements.push(element);
			}
		}
	};
	
	self.setTotalRow = function(o) {
		for (var n in o) {
			if (self.element(n).slValue) {
				self.element(n).slValue.setValue(o[n]);
			} else {
				self.element(n).innerHTML = o[n];
			}
		}
		self.refresh();
	};
	
	self.setAsLoaded = function(loaded) {
		self.loaded = loaded === undefined ? true : loaded;
		if (self.loaded) {
			var element;
			while (element = self.loadingMessageElements.pop()) {
				element.style.opacity = 1;
				element.style.fontStyle = "";
			}
			self.refresh();
			self.scroller.itemLoaded(self);
		}
	};
	
	self.element = function(id) {
		if (typeof(id) != "string") return id;
		var c = sl.getChildNodes(self.el);
		for (var i = 0; i < c.length; i++) {
			if (c[i].getAttribute && c[i].getAttribute("data-slid") == id) return c[i];
		}
		return null;
	};
	
	self.deactivateIfOutOfView = function() {
		if (self.total) return;
		if (self.deactivated != !(self.itemIndex >= self.scroller.firstItem && self.itemIndex <= self.scroller.lastItem)) {
			self.setDeactivated(!self.deactivated);
		}
	};
	
	self.isInView = function() {
		if (self.total) return true;
		return self.itemIndex >= self.scroller.firstItemInView && self.itemIndex <= self.scroller.lastItemInView;
	};
	
	self.setDeactivated = function(deactivated) {
		self.deactivated = deactivated;
		if (self.el) {
			self.el.style.display = deactivated ? "none" : "";
			if (deactivated) {
				self.scroller.dispatchEvent("deactivated",self);
			}
		}		
	};
		
	self.refresh = function() {
		if (!self.scroller) return;
		
		self.updatePosition();
				
		if (self.checkCellBox) {
			self.checkCellBox.checked = self.scroller.isChecked(self);
		}
				
		self.el.className = self.scroller.itemElClass + (self.scroller.viewMode != "list" ? " "+self.scroller.viewMode : (self.itemIndex&1?" odd":""));
		
		if (self.scroller.scrollerTable) {
			var c = self.el.childNodes;
			var col = 0;
			for (var i = 0; i < c.length; i++) {
				if (c[i].nodeName == "DIV" && c[i].className != "icon") {
					c[i].style.width = "";
					self.scroller.declareCellSize(self.itemIndex,col,sl.getTotalElementSize(c[i]));
					col ++;
				}
			}
			self.fixWidth();
		}
		
		
		if (self.scroller.viewMode == "icon" && self.extra._IMAGE != self.iconSrc) {
			sl.removeChildNodes(self.iconEl);
			if (self.extra._IMAGE) {
				var iconImg = sl.dg("",self.iconEl,"img",{"src":self.extra._IMAGE,"className":"icon-image"});
							
				self.iconSrc = self.extra._IMAGE;
				
				function loaded() {
					self.iconEl.style.left = Math.round((self.iconEl.parentNode.offsetWidth-iconImg.offsetWidth)/2)+"px";
				};
				
				if (iconImg.complete) {
					loaded();
				} else {
					iconImg.onload = loaded;
				}
			}
		}
	};
	
	self.updatePosition = function() {		
		if (self.scroller.viewMode == "icon")	{
			self.y = Math.floor(self.itemIndex / self.scroller.iconCols) * self.scroller.iconSize;
			self.el.style.left = ((self.itemIndex % self.scroller.iconCols) * self.scroller.iconSize) + "px";
			self.el.style.top = self.y + "px";
		} else {
			self.y = self.total ? 
			Math.min(self.scroller.itemSize.height * self.scroller.itemCount,self.scroller.scrollY + self.scroller.el.offsetHeight - sl.getTotalElementSize(self.el).height)
				: 
			(self.scroller.itemSize.height * self.itemIndex);
			self.el.style.left = "0px";
			self.el.style.top = self.y + "px";
		}
	};
	
	self.fixWidth = function() {
		for (var i = 0; i < self.scroller.tableColWidth.length; i++) {
			var pad = sl.getTotalElementSize(self.cols[i],true);
			self.cols[i].style.width = (self.scroller.tableColWidth[i] - pad.width)+"px";
		}
	};
	
	self.setExtra = function(n,v) {
		if (!self.extra) {
			self.extra = {};
		} else self.extra[n] = v;
	};
	
	self.getExtra = function(n) {
		return self.extra[n];
	};
	
	self.setValues({
		"y":0,
		"width":0,
		"height":0,
		"extra":{},
		"itemIndex":-1,
		"loaded":false,
		"deactivated":false,
		"checkCellBox":null,
		"cols":[],
		"iconSrc":""
	});
	
	if (o) self.setValues(o);
	
	self.init();
};

sl.scrollerSection = function(o) {
	var self = this;
	sl.initSlClass(this,"scroller-section");
	
	self.init = function() {
		self.el = sl.dg("",self.scroller.sectionContEl,"div",{
			"style":{
				"opacity":0
			},
			"innerHTML":self.name
		});		
		
		self.size = sl.getTotalElementSize(self.el);
		
		sl.addEventListener(self.el,"click",function(){
			self.scroller.scrollToItem(self.index);
		},false);
		
		self.refresh();
	};
	
	self.setY = function(y) {
		self.y = y;
		if (self.size) {
			self.yTop = y - Math.ceil(self.size.height / 2);
			self.yBottom = self.yTop + self.size.height;
		}
	};
	
	self.refresh = function() {
		if (!self.scroller.sectionContEl.offsetHeight) return;
		var nextIndex = self.scroller.nextSectionIndex(self);
		self.count = nextIndex - self.index;
		self.middleIndex = (self.index + nextIndex) / 2;
		self.pos = self.middleIndex / self.scroller.itemCount;

		self.desiredY = Math.min(self.scroller.sectionContEl.offsetHeight - Math.ceil(self.size.height / 2),Math.max(Math.ceil(self.size.height / 2),Math.round(self.pos * self.scroller.sectionContEl.offsetHeight)));
		if (self.y == -1) self.setY(self.desiredY);
		
		self.prev = self.scroller.getRelativeSection(self,-1);
		self.next = self.scroller.getRelativeSection(self,1);
		
		self.repositioner();
	};
	
	self.repositioner = function() {
		var changed = false, prev, next;
		
		self.yForce = (self.desiredY - self.y) / 5;
		
		self.setY(self.y + self.yForce);
		
		if (Math.abs(self.y - self.desiredY) >= 0.5 && self.yForce > 0.1) {		
			changed = true;
		} else {
			self.setY(self.desiredY);
		}
		
		self.show = true;
		prev = self.prev;
		while (prev) {
			if (prev.show && self.yTop <= prev.yBottom && self.count <= prev.count) {
				self.show = false;
				break;
			}
			prev = self.scroller.getRelativeSection(prev,-1);
		}
		
		if (self.show) {
			next = self.next;
			while (next) {
				if (next.show && self.yBottom >= next.yTop && self.count <= next.count) {
					self.show = false;
					break;
				}
				next = self.scroller.getRelativeSection(next,1);
			}
		}

		if (!self.show) {
			var change = 0;
			if (self.prev && self.yTop <= self.prev.yBottom) {
				change = -(self.prev.yBottom - self.yTop) / 4;
			}
			
			if (self.next && self.yBottom >= self.next.yTop) {
				change = -(self.yBottom - self.next.yTop) / 4;
			}
			
			if (change > 0.1) {
				self.setY(Math.round(self.y + change));
			}
		}
		
		self.el.style.top = Math.round(self.y - self.size.height / 2) + "px";
		
		if (self.opacity > 0 && !self.show) {
			self.opacity -= 0.1;
			if (self.opacity < 0) self.opacity = 0;
			changed = true;
		}
		
		if (self.opacity < 1 && self.show) {
			self.opacity += 0.1;
			if (self.opacity > 1) self.opacity = 1;
			changed = true;
		}
				
		if (changed) {
			self.nameUpdate();
			self.el.style.opacity = self.opacity;
			self.el.style.display = self.opacity == 0 ? "none" : "";
			setTimeout(self.repositioner,50);
		}
	};
	
	self.nameUpdate = function() {
		next = self.next, toSect = "";
		while (next && !next.show) {
			toSect = next.name;
			next = self.scroller.getRelativeSection(next,1);
		}
		self.el.innerHTML = self.name + (toSect && self.name.length == 1 ? "-"+toSect : "");
	};
	

	self.destruct = function() {
		if (self.el.parentNode) self.el.parentNode.removeChild(self.el);
	};
				
	self.setValues({
		"desiredY":-1,
		"y":-1,
		"yForce":0,
		"yTop":0,
		"yBottom":0,
		"pos":0,
		"middleIndex":0,
		"size":{},
		"count":0,
		"show":true,
		"opacity":0,
		"repositionerTimer":false
	});
	
	if (o) self.setValues(o);
	
	self.init();
};
