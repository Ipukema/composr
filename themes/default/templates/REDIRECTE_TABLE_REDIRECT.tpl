{$REQUIRE_JAVASCRIPT,redirects_editor}

<tr class="{$CYCLE,results_table_zebra,zebra_0,zebra_1}" data-tpl="redirecteTableRedirect">
	<td>
		<div class="accessibility_hidden"><label for="from_zone_{I*}">{!REDIRECT_FROM_ZONE}</label></div>
		{+START,IF_NON_EMPTY,{FROM_ZONES}}
			<select class="quite_wide_field" id="from_zone_{I*}" name="from_zone_{I*}">{FROM_ZONES}</select>
		{+END}
		{+START,IF_EMPTY,{FROM_ZONES}}
			<input data-cms-invalid-pattern="[^\*\w\.\-]" maxlength="80" size="10" class="quite_wide_field" id="from_zone_{I*}" name="from_zone_{I*}" value="{FROM_ZONE*}" type="text" />
		{+END}
		:
	</td>
	<td>
		<div class="accessibility_hidden"><label for="from_page_{I*}">{!REDIRECT_FROM_PAGE}</label></div>
		<input data-cms-invalid-pattern="[^\*\w\.\-]" maxlength="80" class="quite_wide_field" type="text" id="from_page_{I*}" name="from_page_{I*}" value="{FROM_PAGE*}" />
		&rarr;
	</td>
	<td>
		<div class="accessibility_hidden"><label for="to_zone_{I*}">{!REDIRECT_TO_ZONE}</label></div>
		{+START,IF_NON_EMPTY,{TO_ZONES}}
			<select class="quite_wide_field" id="to_zone_{I*}" name="to_zone_{I*}">{TO_ZONES}</select>
		{+END}
		{+START,IF_EMPTY,{TO_ZONES}}
			<input data-cms-invalid-pattern="[^\*\w\.\-]" maxlength="80" size="10" class="quite_wide_field" id="to_zone_{I*}" name="to_zone_{I*}" value="{TO_ZONE*}" type="text" />
		{+END}
		:
	</td>
	<td>
		<div class="accessibility_hidden"><label for="to_page_{I*}">{!REDIRECT_TO_PAGE}</label></div>
		<input data-cms-invalid-pattern="[^\*\w\.\-]" maxlength="80" class="quite_wide_field" type="text" id="to_page_{I*}" name="to_page_{I*}" value="{TO_PAGE*}" />
	</td>
	<td>
		<div class="accessibility_hidden"><label for="is_transparent_{I*}">{!IS_TRANSPARENT_REDIRECT}</label></div>
		<input data-mouseover-activate-tooltip="['{!IS_TRANSPARENT_REDIRECT;}','40%']" title="{!IS_TRANSPARENT_REDIRECT}" class="input_tick" type="checkbox" id="{NAME*}" name="{NAME*}"{+START,IF,{TICKED}} checked="checked"{+END} value="1" />
	</td>
	<td>
		{+START,IF,{$NEQ,{I},new}}
			<a href="#!" class="js-click-confirm-container-deletion"><img src="{$IMG*,icons/14x14/delete}" srcset="{$IMG*,icons/28x28/delete} 2x" alt="{!DELETE}" title="{!DELETE}" /></a>
		{+END}
	</td>
</tr>
