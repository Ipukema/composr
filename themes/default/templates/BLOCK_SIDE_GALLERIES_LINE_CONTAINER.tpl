{+START,IF_NON_EMPTY,{CONTENTS}}
	<div data-toggleable-tray="{}">
		<span class="side_galleries_block_exp toggleable-tray-title js-tray-header">
			<a class="toggleable-tray-button js-tray-onclick-toggle-tray" href="#!"><img alt="{!EXPAND}: {CAPTION*}" title="{!EXPAND}" src="{$IMG*,1x/trays/expand}" /></a>
			<a class="toggleable-tray-button js-tray-onclick-toggle-tray" href="#!">{CAPTION*}</a>
		</span>

		<ul style="display: none" class="compact_list toggleable-tray js-tray-content">
			{CONTENTS}
		</ul>
	</div>
{+END}
