(function(){	
	var ua = navigator.userAgent, nv = navigator.vendor, np = navigator.platform;
	var vss = null;
	
	function searchString(data) {
		for (var i=0; i<data.length; i++)	{
			vss = data[i][3] || data[i][2];
			if (data[i][0]) {
				if (data[i][0].indexOf(data[i][1]) != -1) {
					return data[i][2];
				}
			} else if (data[i][4]) {
				return data[i][2];
			}
		}
	};
	
	function searchVersion(ds) {
		var index = ds.indexOf(vss);
		if (index == -1) return;
		return parseFloat(ds.substring(index+vss.length+1));
	};
	
	var bDef = [
		[ua,"Chrome","Chrome"],
		[ua,"OmniWeb","OmniWeb","OmniWeb/"],
		[nv,"Apple","Safari","Version"],
		[0,0,"Opera","Version",window.opera],
		[nv,"iCab","iCab"],
		[nv,"KDE","Konqueror"],
		[ua,"Firefox","Firefox"],
		[nv,"Camino","Camino"],
		[ua,"Netscape","Netscape"],
		[ua,"MSIE","IE","MSIE"],
		[ua,"Gecko","Mozilla","rv"],
		[ua,"Mozilla","Netscape","Mozilla"]
	];

	var osDef = [
		[np,"Win","Windows"],
		[np,"Mac","Mac"],
		[ua,"iPhone","iPhone/iPod"],
		[ua,"Android","Android"],
		[np,"Linux","Linux"]
	];
	
	sl.browser = searchString(bDef) || "An unknown browser";
	sl.version = searchVersion(navigator.userAgent) 
		|| searchVersion(navigator.appVersion)
		|| "an unknown version";
	sl.OS = searchString(osDef) || "an unknown OS";
	
})();
