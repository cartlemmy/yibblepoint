<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	
	<title><?=$this->getTitle();?></title>
	<meta name="description" content="<?=$this->getDescription();?>">
	
	<link href="css/bootstrap.min.css" rel="stylesheet">
	<link href="css/blank/style.css" rel="stylesheet">
		
	<script type="text/javascript">var sl = {};</script>
</head>
<body>
	<script
  src="https://code.jquery.com/jquery-3.2.1.min.js"
  integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4="
  crossorigin="anonymous"></script>
	 <script src="js/bootstrap.min.js"></script
		
	
			<div class="content"><div class="container main">
				<div>
					<?=$this->getContent();?> 
				</div>
			</div></div>

	</div></div>
</body>
</html><?php

$this->addCacheVar('$_SESSION["userID"]');
