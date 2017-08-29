<?php

class buyapowa extends slWebModule {
	private $market;
	
	public function init($market) {
		$this->market = $market;
	}
	
	public function getAlias() {
		return "buyapowa";
	}
	
	public function track($params) {
		?><script type="text/javascript">
			var buyapowa = new Buyapowa("https://<?=$this->market;?>.co-buying.com/");
			buyapowa.track(<?=json_encode($params);?>);
		</script><?php
	}
	
	public function embedInviteAFriend() {
		echo '<div id="bp_div"></div>';
		$this->show(array("bp_div"),"embedInviteAFriend()");
	}
	
	public function show($params,$function = false) {
		array_unshift($params,"https://".$this->market.".co-buying.com/");
		?><script type="text/javascript">
			var buyapowa = new Buyapowa(<?=substr(json_encode($params),1,-1);?>);
			<?=$function?"buyapowa.".$function.";":"";?>
		</script><?php
	}	
}
