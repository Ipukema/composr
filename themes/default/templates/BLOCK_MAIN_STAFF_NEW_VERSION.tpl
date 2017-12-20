<section id="tray_{!VERSION_ABOUT|,{VERSION}}" data-toggleable-tray="{ save: true }" class="box box___block_main_staff_new_version">
	<h3 class="toggleable-tray-title js-tray-header">
		<a class="toggleable-tray-button js-tray-onclick-toggle-tray" href="#!"><img alt="{!CONTRACT}: {$STRIP_TAGS,{!VERSION_ABOUT,{VERSION*}}}" title="{!CONTRACT}" src="{$IMG*,1x/trays/contract2}" /></a>

		<a class="toggleable-tray-button js-tray-onclick-toggle-tray" href="#!">{!VERSION_ABOUT,{VERSION*}}</a>
	</h3>

	<div class="toggleable-tray js-tray-content">
		<div class="staff-new-versions">
			{VERSION_TABLE}

			{+START,IF,{HAS_UPDATED_ADDONS}}
				<p class="red_alert">
					{!addons:SOME_ADDONS_UPDATED,{$PAGE_LINK*,_SEARCH:admin_addons}}
				</p>
			{+END}

			<div class="img-wrap">
				<img src="{$IMG*,product_logo}" alt="" />
			</div>
		</div>
	</div>
</section>
