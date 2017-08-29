<!DOCTYPE html>
<html lang="<?=$this->get("language");?>">
	<meta charset="<?=$this->getCharset();?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
		
	<title><?=$this->getTitle();?></title>
	
	<?=$this->getMetaTags("\t");?>	
	
	<link rel="canonical" href="<?=$this->getCanonicalLink();?>" />

	<?php 
	$this->addCSS("css/bootstrap.css"); 
	$this->addCSS("css/document.css");	
	
	$this->addScript("//code.jquery.com/jquery-1.11.2.min.js");
	$this->addScript("//maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js");
	
	?>	
	<script type="text/javascript">var sl = {config:{}};</script>
</head>
<body style="-webkit-print-color-adjust:exact;">
	<?php $this->bodyStart();?>
	<div class="container"><div<?=$this->get('document-margin') ? ' style="margin:'.$this->get('document-margin').'"' : '';?>>
		<?=$this->getContent();?> 
	</div></div>
	<?php $this->bodyEnd();?>
</body>
</html>
