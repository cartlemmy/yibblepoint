var cmPath = "../inc/thirdparty/codemirror/";

sl.require([cmPath+"lib/codemirror.js",cmPath+"lib/codemirror.css"],function(){
sl.require([cmPath+"mode/xml/xml.js",cmPath+"mode/javascript/javascript.js",cmPath+"mode/css/css.js",cmPath+"mode/htmlmixed/htmlmixed.js",cmPath+"mode/clike/clike.js",cmPath+"mode/php/php.js"],function(){
self.require(["editor.css"],function(){
	var pageData = {}, changedPageData = {}, editingPage, pages,
	wysiwygFocused = false;

	self.createView({
		"contentPadding":"0px",
		"tools":["menu","save"]
	});

	self.view.setMenu([
		{"label":"en-us|+ New","action":"new"}
	]);
	
	self.view.addEventListener("menu-click",function(type,o){
		switch (o.item.action) {
			case "new":
				newPage();
				break;
		}
	});
		
	self.view.setContentFromHTMLFile();
	self.view.maximize();

	var hLayout = self.view.element("siteEditorFrames");

	var cmSource = CodeMirror.fromTextArea(
		self.view.element("source"),
		{"mode":"php","lineNumbers":true}
	);
	
	cmSource.setSize("100%","100%");
	/*hLayout.addEventListener("resize",function() {
		console.log(parameters);
	});*/
	
	var hView;
	function refreshPages() {
		self.request("getPages",[true],function(res){

			pages = res;
			var icons = ["contentFile","dynamicFile","subContent","showInNav"];
			
			if (hView) {
				hView.data = res;
				hView.refresh();
			} else {
				hView = new sl.heirarchicalView({
					"el":self.view.element("pages").slSpecial.contentEl,
					"data":res,
					"clickable":true,
					"callback":function(name,o){
						var cont = sl.dg("",null,"span");
						var c = [];
						
						c.push(o.title ? o.title : "("+name+")");
						
						for (var i = 0; i < icons.length; i++) {
							if (o[icons[i]] && !(icons[i] == "contentFile" && o.noContent)) c.push('<div class="s-editor-'+icons[i].camelCaseToReadable("-").toLowerCase()+'" title="'+icons[i].camelCaseToReadable()+(typeof(o[icons[i]]) == "string"?": "+o[icons[i]].escapeHtml().split("/").pop():'')+'"></div>');
						}
						
						cont.innerHTML = c.join(" ");
						
						return cont;
					}
				});
				
				hView.addEventListener("click",function(t,name){
					openPage(name);
				});
			}
		
			refreshParentPageSelect();
		});
	};
	
	refreshPages();
	
	function response(res) {
		if (res.refresh) refreshPages();
	};
	
	function save() {
		if (pageData.urlName == "") {
			alert("en-us|Page needs a URL Name.");
			return;
		}
		
		self.view.setSaveState("saving");
						
		self.request("setPage",[pageData.urlName,changedPageData],function(res) {
			editingPage = pageData.urlName;
			self.view.setSaveState("saved");
			response(res);
			if (res.history) updateHistory(res.history);
		});
	};
		
	self.view.save = save;
		
	function setAllPageData(data) {
		var def = {
			"content":"","description":"","title":"","urlName":"","order":0,
			"parent":"","showInNav":false,"titleTag":"","meta.og:title":"",
			"meta.og:description":"","meta.og:type":"","meta.og:image":""
		};
		for (var n in def) {
			//if (data[n] == undefined) data[n] = def[n];
			if (sl.getDeepRef(data,n) == undefined) sl.setDeepRef(data,n,def[n]);
		}
		
		self.view.element("source").disabled = !!data.noContent;
		self.view.element("del").style.display = data.dynamicFile ? "none" : "";
		
		changedPageData = {};
		pageData = data;
		pageData.contentEl = null;
		
		for (var n in def) {
			setPageData(n,sl.getDeepRef(data,n),true);
		}
	};
	
	
	var PHPUpdateTimer = null, PHPPreviewCNT = 0, PHPPreviewContent = "", PHPPreviewRes = [], PHPCode = [];
	
	function PHPUpdate() {
		self.request("getPHPPreview",[pageData.urlName,PHPPreviewContent],function(res){
			if (!self.view.element("preview")) return;
			if (sl.typeOf(res) != "array") res = [];
			PHPPreviewRes = res;
			PHPUpdateApplyHTML();
		});
	}
  
  function PHPUpdateApplyHTML() {
    var doc = self.view.element("preview").contentWindow.document;    
    for (var i = 0; i < PHPPreviewCNT; i++) {
			if (doc.getElementById('preview-php-'+i)) doc.getElementById('preview-php-'+i).innerHTML = PHPPreviewRes[i] ? PHPPreviewRes[i] : '';
		}
  }
  
	function parsePreviewHTML(v) {
		PHPPreviewContent = v;
		v = v.split("<?php");

		PHPPreviewCNT = 0;
		for (var i = 1; i < v.length; i++) {
			var a = v[i].split("?>");
	  while (PHPCode.length<i) {PHPCode.push("");}
			PHPCode[i-1] = "<?php"+a.shift()+"?>";
			var html = a.length ? a.join("?>") : "";
			v[i] = '<div id="preview-php-'+(PHPPreviewCNT++)+'" class="prev-php">'+(PHPPreviewRes[PHPPreviewCNT-1] ? PHPPreviewRes[PHPPreviewCNT-1] : '')+'</div><!--END-PHP-PREV-->' + html;
		}
		if (v.length > 1) {
			if (PHPUpdateTimer) clearTimeout(PHPUpdateTimer);
			PHPUpdateTimer = setTimeout(PHPUpdate,1000);
		}
		return v.join("");
	}
  
  function restorePHPFromWYSIWYG(v) {
    var v = v.split(/\<div\s+id\=\"preview\-php\-(\d+)\" class\=\"prev\-php\"\>/);
    for (var i = 1; i < v.length; i+=2) {
      v[i] = PHPCode[Number(v[i])];
      if (v[i+1].indexOf("<!--END-PHP-PREV-->") != -1) v[i+1] = v[i+1].split("<!--END-PHP-PREV-->").pop();
    }
    return v.join("");
  }
	
	self.wysiwygEvent = function(type,v) {
		switch (type) {
			case "change":
				setPageData("content",v,false,true)
				break;
			
			case "focus":
				wysiwygFocused = true;
				break;
				
			case "blur":
				wysiwygFocused = false;
				break;
        
		}		
	};
	
	var oldUrlName;
	function setPageData(n,v,fromAll,fromWYSIWYG) {		
		var el;
		switch (n) {		
			case "source":
				n = "content";
				//v = v.wikify(true);
				if (pageData.contentEl) pageData.contentEl.innerHTML = parsePreviewHTML(v);
				break;
				
			case "title":
				if (el = self.view.element(n)) {
					if (document.activeElement != el) el.value = v ? v : "";
					if (pageData.urlName == "" || pageData.urlName == oldUrlName) setPageData("urlName",v.safeName());
					oldUrlName = v.safeName();
				}
				break;
				
			case "content":
				//self.view.element("source").value = v;// ? v.dewikify() : ""
				v = restorePHPFromWYSIWYG(v);
				cmSource.setValue(v);
				
				if (sl.config.web.wysiwgEditor) {
					if (!fromWYSIWYG) {
						if (self.previewWindow && self.previewWindow.wysiwygEditor) {
							self.previewWindow.wysiwygEditor.setContent(parsePreviewHTML(v));
						} else if (pageData.contentEl) {
							pageData.contentEl.innerHTML = parsePreviewHTML(v);
						}
					}
				} else if (pageData.contentEl) {
				  pageData.contentEl.innerHTML = parsePreviewHTML(v);
				} else parsePreviewHTML(v);
				break;
			
			case "history":
				updateHistory(v);
				break;
				
			case "urlName":
				v = v.safeName();
							
			default:
				if ((el = self.view.element(n)) && document.activeElement != el) {
					if (el.slSpecial) {
						el.slSpecial.setValue(v);
					} else if (el.nodeName == "SELECT") {
						for (var i = 0; i < el.options.length; i++) {
							if (v == el.options[i].value) {
								el.selectedIndex = i;
								break;
							}
						}
					} else {
						if (el.type == "checkbox") {
							el.checked = !!v;
						} else {
							el.value = v ? v : "";
						}
					}
				}
				break;
		}
		sl.setDeepRef(pageData,n,v);
		if (!fromAll) {
			sl.setDeepRef(changedPageData,n,v);
			self.view.setSaveState("unsaved");
		}
	};
	
	var fields = [
		"source","title","urlName","showInNav","description","parent",
		"titleTag","meta.og:title","meta.og:description","meta.og:type",
		"meta.og:image"
	];
	
	for (var i = 0; i < fields.length; i++) {
		(function(n,field){
			if (field) {
				function change() {
					if (field.nodeName == "SELECT") {
						setPageData(n,field.options[field.selectedIndex].value);
					} else {
						if (field.type == "checkbox") {
							setPageData(n,field.checked);
						} else {
							setPageData(n,field.value);
						}
					}
				};
				
				if (field.slSpecial) {
					field.slSpecial.listener = self;
				} else if (n == "source") {
					cmSource.on("change", function(){
						if (!wysiwygFocused) setPageData(n,cmSource.getValue());
					});
					
					cmSource.on("focus", function(){
						wysiwygFocused = false;
					});
				} else {
					field.addEventListener("change",change);
				}
				
				if (field.nodeName != "SELECT") {
					var t;
					field.addEventListener("keyup",function(){
						if (t) clearTimeout(t);
						var t = setTimeout(change,100);
					});
				}
			}
		})(fields[i],self.view.element(fields[i]));
	}
	
	self.addEventListener("change",function(t,o){
		if (o.field) setPageData(o.field,o.value);
	});
						
	function updateHistory(hist) {
		var cont = self.view.element("history");
		sl.removeChildNodes(cont);
		
		for (var i = 0; i < hist.length; i++) {
			(function(histItem){
				var div = sl.dg("",cont,"div");
				sl.dg("",div,"text",sl.date(sl.config.international.date+" "+sl.config.international.time,histItem.edited)+" by "+histItem.editedBy);
				var aRestore = sl.dg("",div,"a",{"style":{"marginLeft":"15px"},"href":"javascript:;","innerHTML":"en-us|Restore"});
				aRestore.addEventListener("click",function(){
					self.request("getPage",[editingPage,histItem.file],function(data){
						self.view.element("preview").src = sl.config.webRoot+(data.relatedPage?data.relatedPage:editingPage)+".html?fromEditor="+(data.relatedPage?editingPage:'1')+"&hist="+histItem.file;
						setAllPageData(data);
					});				
				});		
			})(hist[i]);
		}
	};
	
	function checkForUnsaved() {
		if (self.view.saveState == "unsaved") {
			return confirm("en-us|The current page has unsaved changes, are you sure you want to DISCARD those changes?");
		}
		return true;
	};
	
	function openPage(name) {
		if (editingPage == name) return;
		if (checkForUnsaved()) {
			editingPage = name;
			self.request("getPage",[name],function(data){
				self.view.element("preview").src = sl.config.webRoot+(data.relatedPage?data.relatedPage:name)+".html?fromEditor="+(data.relatedPage?name:'1');
				setAllPageData(data);
				self.view.setSaveState("saved");
			});
		}
	};	
	
	function newPage() {
		if (checkForUnsaved()) {
			editingPage = "";
			setAllPageData({"urlName":""});
			self.view.setSaveState("new");
			self.view.element("preview").src = sl.config.webRoot+"_BLANK.html?fromEditor=1";
		}
	};
	
	function refreshParentPageSelect() {
		var el = self.view.element("parent");
		
		sl.removeChildNodes(el);
		
		sl.dg("",el,"option",{
			"value":"",
			"innerHTML":"NONE"
		});
					
		function build(pages,level) {
			for (var n in pages) {
				if (!pages[n].subContent && n != editingPage) {
					sl.dg("",el,"option",{
						"value":n,
						"innerHTML":("&nbsp;").repeat(level*4)+(pages[n].title ? pages[n].title : "("+n+")")
					});
					if (pages[n].children) build(pages[n].children,level+1);
					if (pageData.parent == n) el.selectedIndex = el.options.length - 1;
				}
			}
		};
		build(pages,0);		
	};
	
	self.view.element("del").addEventListener("click",function() {
		if (confirm(sl.format("en-us|Are you sure you want to delete this page (%%)?\n\n",editingPage))) {
			self.request("deletePage",[editingPage],function(res) {
				newPage();
				response(res);
			});
		}
	});
	
	self.view.element("preview").addEventListener("load",function() {
		self.previewWindow = self.view.element("preview").contentWindow;
		var doc = self.previewWindow.document;
		pageData.contentEl = doc.getElementById("editor-content");
		self.previewWindow.slApp = self;
    if (self.previewWindow.wysiwygEditor) {
      self.previewWindow.wysiwygEditor.setContent(parsePreviewHTML(pageData.content));
      PHPUpdateApplyHTML();
    }
	});
	
	newPage();
});
});
});
