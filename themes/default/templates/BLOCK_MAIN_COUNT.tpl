<div class="hit_counter" data-tpl-counting-blocks="blockMainCount" data-tpl-args="{+START,PARAMS_JSON,UPDATE}{_*}{+END}">
	<div class="box box___block_main_count"><div class="box_inner">
		{+START,IF,{$LT,{$LENGTH,{VALUE}},2}}0{+END}{+START,IF,{$LT,{$LENGTH,{VALUE}},3}}0{+END}{+START,IF,{$LT,{$LENGTH,{VALUE}},4}}0{+END}{+START,IF,{$LT,{$LENGTH,{VALUE}},5}}0{+END}{VALUE*}
	</div></div>
</div>