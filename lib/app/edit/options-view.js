self.addEventListener("appeared",function(){
	sl.require("js/ui/subItemView.js",function(){
		var isInit = false, siv;
		
		function init(id) {
			isInit = true;
			self.setContentFromHTMLFile();

			self.formatContent(app.info.setup.optionGroup.name);

			self.dbId = id;
			
			siv = new sl.subItemView({
				"app":app,
				"scroller":app.view.element("options-scroller").slSpecial,
				"table":self.info.setup.table
			});
		}
		
		function setDBId(id) {
			if (id) {
				if (!isInit) init(id);
		
				var filter = {};
				filter[app.info.setup.optionGroup.parent] = id;
				siv.setFilter(filter);
			}
		};

		app.addEventListener("id-set",function(t,id){
			setDBId(id);
		});
		setDBId(app.info.data._KEY)
	});
});
