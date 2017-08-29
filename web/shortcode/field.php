<?php

//params: type, name, label, value, options, id

if ($params[0] == "submit") {
	?><div style="margin:10px;"><button type="submit" name="submit" value="1" class="btn btn-default">Submit</button></div><?php
	return;
}

$options = isset($params[4]) ? explode(";",$params[4]) : array();
$id = isset($params[5]) ? $params[5] : $params[1];
$value = isset($params[3]) ? $params[3] : false;
$label = (in_array("required",$options)?"<div class='required' title='Required Field'>* </div>":"").htmlspecialchars($params[2]);

if (!isset($GLOBALS["slFormData"])) $GLOBALS["slFormData"] = array("fields"=>array());
$GLOBALS["slFormData"]["fields"][] = array(
	"type"=>$params[0],
	"name"=>$params[1],
	"label"=>$params[2],
	"value"=>$value,
	"options"=>$options,
	"id"=>$id
);

switch ($params[0]) {
	case "country":
		require_once(SL_WEB_PATH."/inc/paliDB/class.paliRegistration.php");
		if (!isset($GLOBALS["PALI_REG"])) $GLOBALS["PALI_REG"] = new paliRegistration();
		
		if (!$value) $value = $GLOBALS["PALI_REG"]->getDefaultCountry();
	
		$crn = isset($params[6]) ? $params[6] : "country-region";
		?><div class="form-group" style="margin:0 10px">
			<label for="<?=$id;?>" class="control-label"><?=$label;?></label>
		<select class="form-control" id="<?=$id;?>" name="<?=$params[1];?>" onchange="if(window.countryChange)countryChange('<?=$crn;?>')"><?php
			paliRegSelectOptions( $GLOBALS["PALI_REG"]->getCountries(),$value);
		?></select>
		</div>
		<script>
		if (!window.countryRegionEls) window.countryRegionEls = {};
		if (!window.countryRegionEls['<?=$crn;?>']) window.countryRegionEls['<?=$crn;?>'] = {};
		window.countryRegionEls['<?=$crn;?>'].country = document.getElementById('<?=$id;?>');
		</script><?php
		break;
	
	case "region":
		$crn = isset($params[6]) ? $params[6] : "country-region";
		if (!defined("REGION_JS_INCLUDED")) {
			?><script>
				var countriesRegions = <?=json_encode($GLOBALS["PALI_REG"]->getAllRegions(in_array("region-name",$options)));?>;
				
				function regionSelect(crn) {
					var i, els = window.countryRegionEls[crn];
					if (!els) return;
					
					for (i = 0; i < els.regionSel.options.length; i++) {
						if (els.regionSel.options[i].value == els.region.value) els.regionSel.selectedIndex = i;
					}
				}
				
				function countryChange(n) {
					function addOpt(n,v) {
						var opt = document.createElement('option');
						opt.appendChild(document.createTextNode(v));
						opt.value = n;
						
						els.regionSel.appendChild(opt);
					}
					
					var els = window.countryRegionEls[n];
					if (!els) return;
					
					var cc =  els.country.options[els.country.selectedIndex].value, region, i;
					
					if ((region = countriesRegions[cc]) && typeof(region) == "object") {
						els.regionSel.style.display = "";
						els.region.type = "hidden";
						
						els.regionSel.selectedIndex = 0;
						
						while (els.regionSel.options.length) {
							els.regionSel.removeChild(els.regionSel.options[0]);
						}
						
						var found = -1;
						
						addOpt("","Select one..");
						if (region.length) {
							for (i = 0; i < region.length; i++) {
								if (els.region.value == region[i]) found = els.regionSel.options.length;
								addOpt(region[i],region[i]);
							}
						} else {
							for (i in region) {
								if (els.region.value == i) found = els.regionSel.options.length;
								addOpt(i,region[i]);
							}
						}
						
						addOpt("other","Other...");
						if (found == -1) {
							els.region.value = "";
						} else {
							els.regionSel.selectedIndex = found;
						}
					} else {
						els.regionSel.style.display = "none";
						els.region.type = "text";
					}
				}
				
				function regionChange(n) {
					var els = window.countryRegionEls[n];
					if (!els) return;
					
					var v = els.regionSel.options[els.regionSel.selectedIndex].value;
					if (els.regionSel.selectedIndex != els.regionSel.options.length - 1) {
						if (v != "") els.region.value = v;
						els.region.type = "hidden";
					} else {
						els.region.value = "";
						els.region.type = "text";
					}
				}
			</script>
			<?php
			define("REGION_JS_INCLUDED",1);
		}
		
		?><div class="form-group" style="margin:0 10px">
			<label for="<?=$id;?>" class="control-label"><?=$label;?></label>
		<select class="form-control" id="<?=$id;?>-sel" data-crn="<?=$crn;?>" onchange="regionChange('<?=$crn;?>')"></select>
		<input type="hidden" class="form-control" id="<?=$id;?>" data-crn="<?=$crn;?>" name="<?=$params[1];?>" value="<?=($value?htmlspecialchars($value):"");?>">
		</div>
		<script>
		if (!window.countryRegionEls) window.countryRegionEls = {};
		if (!window.countryRegionEls['<?=$crn;?>']) window.countryRegionEls['<?=$crn;?>'] = {};
		window.countryRegionEls['<?=$crn;?>'].regionSel = document.getElementById('<?=$id;?>-sel');
		window.countryRegionEls['<?=$crn;?>'].region = document.getElementById('<?=$id;?>');
		countryChange('<?=$crn;?>');

		</script>
		<?php
		break;
	
	case "textarea":
		?><div class="form-group" style="margin:0 10px">
		<label for="<?=$id;?>"><?=$label;?></label>
		<textarea name="<?=$params[1];?>" id="<?=$id;?>" class="form-control" rows="8"><?=($value?htmlspecialchars($value):"");?></textarea>
	</div><?php
		break;
		
	default:
		?><div class="form-group" style="margin:0 10px">
			<label for="<?=$id;?>" class="control-label"><?=$label;?></label>
			<input type="text" class="form-control" id="<?=$id;?>" name="<?=$params[1];?>" value="<?=($value?htmlspecialchars($value):"");?>">
		</div><?php
		break;	
}
