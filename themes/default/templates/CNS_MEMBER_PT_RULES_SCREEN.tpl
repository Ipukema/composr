{TITLE}

<p>
	{!PT_RULES_PAGE_INTRO,{$DISPLAYED_USERNAME*,{USERNAME}}}
</p>

<div class="box box___cns_member_pt_rules_screen"><div class="box-inner">
	{RULES}
</div></div>

<form title="{!PRIMARY_PAGE_FORM}" action="{URL*}" method="post" autocomplete="off">
	{$INSERT_SPAMMER_BLACKHOLE}

	<p class="proceed_button">
		<input accesskey="u" data-disable-on-click="1" class="button_screen buttons--proceed" type="submit" value="{!PROCEED}" />
	</p>
</form>
