function playVideo(id) {
	$('#'+id).show();
	$('#'+id+' iframe')[0].src = "https://www.youtube.com/embed/"+id+"?rel=0&autoplay=1";
}

$('.youtube button').click(function(e){
	var el = e.target;
	while (el && !el.id) {
		el = el.parentNode;
	}
	$('#'+el.id).hide();
	$('#'+el.id+' iframe')[0].src = "about:blank";
});

