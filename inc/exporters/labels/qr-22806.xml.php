<?xml version="1.0" encoding="UTF-8"?>
<document orientation="L" unit="in" size="Letter">
	<config>
		{
			"labels-per-page":12,
			"qr-width":2
		}
	</config>
	<?php $this->block('page'); ?>
	<page topmargin="0.625in" leftmargin="0.625in" rightmargin="0.625in" bottommargin="0.625in" fontsize="9" linewidth="0.1mm">
		<?php $this->block('label',function($page, $label, $row){ ?>
		<label border="1" width="2in" height="2in">
		</label>
		<?php }); ?>
	</page>
	<?php $this->blockEnd(); ?>
</document>
