<?php

require_once(SL_WEB_PATH."/inc/store/class.store.php");
require_once(SL_WEB_PATH."/inc/store/class.storeItem.php");
require_once(SL_WEB_PATH."/inc/store/class.storeOrder.php");

function storeOrderitem_update($id,$n,$v) {
	$item = new storeItem("OI:".(int)$id);
	$item->setOI($n,$v);
	$item->apply();
}

function storeOrderitem_statusCol($id,$n,$v) {
	$item = new storeItem("OI:".(int)$id);

	switch ($n) {
			case "pos":
				$item->setOI("posEntered",$v ? 1 : 0);
				break;
			
			case "lowerOffice":
				$item->setOI("lowerOffice",$v ? 1 : 0);
				break;
				
			case "delivered":
				if ($v) {
					$item->setOI("oldStatus",$item->getOI("status"));
					$item->setOI("status","delivered");
				} else {
					$os = $item->getOI("oldStatus");
					$item->setOI("status", $os ? $os : "");
				}
				break;
	}
	$item->apply();
}

function storeOrderitem_markAsPrinted($ids) {
	foreach ($ids as $id) {
		$GLOBALS["slCore"]->db->update("db/storeOrderItems", array("notePrinted"=>1), array("id"=>$id));
	}
}
