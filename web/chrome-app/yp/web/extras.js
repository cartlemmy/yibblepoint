function expandableDivClick(e,el) {
	var firstChild, c = el.childNodes;
	
	for (var i = 0; i < c.length; i++) {
		if (c[i].nodeType == 1) {
			firstChild = c[i];
			break;
		}
	}
	
	function inFirstChild() {
		var t = e.target;
		while (t != el) {
			if (t == firstChild) return true;
			t = t.parentNode;
		}
		return false;
	};
	
	if ((e.target.nodeName == "INPUT" && e.target.type == "submit") || e.target.nodeName == "A") return true;
	if (e.target == el || !inFirstChild()) {
		firstChild.style.display = firstChild.style.display == 'none' ? '' : 'none';
	}
	return false;
};

var onSizeChangeCb = [], onSizeChangeListeners = false;
function sizeChangeDo() {
	for (var i = 0; i < onSizeChangeCb.length; i++) {
		onSizeChangeCb[i]();
	}
};

function onSizeChange(cb) {
	onSizeChangeCb.push(cb);
	if (!onSizeChangeListeners) {
		window.addEventListener("load",sizeChangeDo);
		window.addEventListener("resize",sizeChangeDo);
		onSizeChangeListeners = true;
	}
};

function dg(e) {
	var d = document.getElementById(e);

	if (d) {
		d.width = d.offsetWidth;
		d.height = d.offsetHeight;
		return d;
	}
	return null;
};
