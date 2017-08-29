self.require("task.js",function(){
	
	self.createView({
		"contentPadding":"0px",
		"minWidth":480,
		"minHeight":360,
		"widget":true
	});

	self.activeTask = 0;
	var tasks = [];
	
	function getTaskById(id) {
		for (var i = 0; i < tasks.length; i++) {
			if (tasks[i].id == id) return tasks[i];
		}
		return null;
	};
	
	function updateTask(o,taskEvent) {
		if (o._KEY) o.id = o._KEY;
		var task = getTaskById(o.id);
		
		if (!task && o.status && Number(o.status) > 2) return;
				
		if (o.state !== false) {
			if (!task) {
				if (o.status === undefined) return;
				task = new sl.task(o);
				task.pipeEvent("state-change",self);
				tasks.push(task);
				task.init();
			} else {
				task.setValues(o);
			}
			updateTaskText(task);
		}
		
		if (o.state === false && task) {
			task.destruct();
			tasks.splice(tasks.indexOf(task),1);
		} else {
			if (taskEvent) {
				if (taskEvent.state === false) {
					task.removeEvent(taskEvent);
				} else {
					task.updateEvent(taskEvent);
				}
			}
		}		
	};

	function update(n,id,o) {
		if (o.assignedTo !== undefined && o.assignedTo != sl.config.userID) {
			
			//console.log('not your task',n,id,o.assignedTo);
			o.state = false;
			updateTask(o);
			return;
		}
		
		if (o.dueTs !== undefined) o.dueTs = Number(o.dueTs);
		if (o.startTs !== undefined) o.startTs = Number(o.startTs);
		if (o.endTs !== undefined) o.endTs = Number(o.endTs);
		
		if (n == "taskEvent") {
			self.request("getTask",[o.task],function(res){
				if (res.dueTs !== undefined) res.dueTs = Number(res.dueTs);
				if (res.startTs !== undefined) res.startTs = Number(res.startTs);
				if (res.endTs !== undefined) res.endTs = Number(res.endTs);
				updateTask(res,o);
			});
			return;
		}		
		updateTask(o);	
	};
	
	function updateTaskText(task) {
		if (task.taskEl) task.taskEl.innerHTML = task.description ? task.description : '';
		if (task.dueEl) task.dueEl.innerHTML = task.dueText();
	}

	self.request("getTasks",[],function(res){
		if (!res) return;
		var i;
		for (i = 0; i < res.tasks.length; i++) {
			update("task",res.tasks[i].id,res.tasks[i]);
		}
		/*
		for (i = 0; i < res.taskEvents.length; i++) {
			update("taskEvent",res.taskEvents[i].id,res.taskEvents[i]);
		}*/
	});
	
	var states = ["active","overdue","due-today","todo","future"];
	
	self.addEventListener("state-change",function(t,task) {
		if (!task) return;

		if (task.state == "remove") {
			if (task.dispEl && task.dispEl.parentNode) task.dispEl.parentNode.removeChild(task.dispEl);
		} else {
			if (task.dispState != task.state) {
				
				if (task.dispEl && task.dispEl.parentNode) task.dispEl.parentNode.removeChild(task.dispEl);
				task.dispEl = sl.dg("",self.view.element(task.state+"-body"),"tr");
								
				var td = sl.dg("",task.dispEl,"td",{"style":{"cursor":"pointer"}});
				task.taskEl = sl.dg("",td,"div",{"className":"ellipsis","style":{"width":sl.innerSize(td).width+"px"}});
				
				td.addEventListener("click",function(){
					self.core.open("edit/?db/userEvent&"+task.id);
				});
				
				td = sl.dg("",task.dispEl,"td");
				task.dueEl = sl.dg("",td,"div");
				task.dispState = task.state;
				
				td = sl.dg("",task.dispEl,"td");
				
				switch (task.state) {
					case "active":
						task.doEl = sl.dg("",td,"button",{"innerHTML":"[icon:check]"});
						task.doEl.addEventListener("click",function(){
							self.request("completeTask",[task.id],function(res){
								
							});
						});
						
						task.pauseEl = sl.dg("",td,"button",{"innerHTML":"[icon:pause]","style":{"margin-left":"4px"}});
						task.pauseEl.addEventListener("click",function(){
							self.request("pauseTask",[task.id],function(res){
								
							});
						});
						break;
						
					case "overdue": case "due-today": case "todo": case "future":
						task.delEl = sl.dg("",td,"button",{"innerHTML":"[icon:trash]"});
						task.delEl.addEventListener("click",function(){
							if (confirm("en-us|Are you sure you want to delete this task?")) {
								self.request("delTask",[task.id],function(res){
									
								});
							}
						});
						
						task.doEl = sl.dg("",td,"button",{"innerHTML":"[icon:play]","style":{"margin-left":"4px"}});
						task.doEl.addEventListener("click",function(){
							self.request("startTask",[task.id],function(res){
								
							});
						});
						break;
				}
				
			}
			updateTaskText(task);
		}
		
		for (var i = 0; i < states.length; i++) {
			self.view.element(states[i]+"-head").style.display = self.view.element(states[i]+"-body").childNodes.length ? "" : "none";
		}
		
		self.activeTask = 0;
		for (i = 0; i < tasks.length; i++) {
			if (tasks[i].state == "active") {
				self.activeTask = tasks[i].id;
				break;
			}
		}
		
		self.view.element('new-task').style.display = self.activeTask ? "none" : "";
		
		for (i = 0; i < tasks.length; i++) {
			switch (tasks[i].state) {
				case "overdue": case "due-today": case "todo": case "future":
					tasks[i].doEl.style.display = self.activeTask ? "none" : "";
					break;
			}
		}
	});
	
	self.refreshListener = self.addServerListener("refresh-db/userEvent",function(res){
		self.request("getTasks",[],function(res){
			var i;
			
			for (i = 0; i < tasks.length; i++) {
				tasks[i].del = true;
			}
			
			for (i = 0; i < res.tasks.length; i++) {
				var task = getTaskById(res.tasks[i].id);
				if (task) {
					res.tasks[i].del = false;
					update("task",res.tasks[i].id,res.tasks[i]);
				}
			}
			
			for (i = 0; i < tasks.length; i++) {
				if (tasks[i].del) tasks[i].destruct();
			}
		});
	});
			
	self.taskListener = self.addServerListener("change-db/userEvent/*",function(res){
		update("task",res._KEY,res);
	});

	self.taskEventListener = self.addServerListener("change-db/taskEvent/*",function(res){
		update("taskEvent",res._KEY,res);
	});
	
	self.addEventListener("destruct",function() {
		self.removeServerListener(self.refreshListener);
		self.removeServerListener(self.taskListener);
		self.removeServerListener(self.taskEventListener);
	});
	
	self.view.setContentFromHTMLFile();
	
	self.view.element('new-task').addEventListener("click",function(){
		self.request("newTask",[],function(res){
			self.core.open("edit/?db/userEvent&"+res);
		});
	});
	
	self.view.element('new-todo').addEventListener("click",function(){
		self.request("newTodo",[],function(res){
			self.core.open("edit/?db/userEvent&"+res);
		});
	});
	
	self.view.element('new-today').addEventListener("click",function(){
		self.request("newToday",[],function(res){
			self.core.open("edit/?db/userEvent&"+res);
		});
	});
	
});
