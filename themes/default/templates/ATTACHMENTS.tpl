{$SET,IMAGE_TYPES,{IMAGE_TYPES}}

{+START,IF,{$BROWSER_MATCHES,simplified_attachments_ui}}
	<div id="attachment_store" class="accessibility_hidden">
		{$,plupload will attach upload code to here}
		<input id="attachment_upload_button" name="attachment_upload_button" value="{!UPLOAD}" type="button" />
	</div>

	<div id="attachment_progress_bars">
		<div id="fsUploadProgress" class="progressBars">
		</div>
	</div>

	<script>// <![CDATA[
		var attachment_template='{ATTACHMENT_TEMPLATE;^/}';
		var max_attachments={MAX_ATTACHMENTS%};
		var num_attachments=1;

		function start_simplified_upload(posting_field_name)
		{
			if (posting_field_name!='{POSTING_FIELD_NAME;/}') return false;

			prepare_simplified_file_input('attachment_multi','file'+window.num_attachments,null,'{POSTING_FIELD_NAME;/}'{+START,IF_PASSED,FILTER},'{FILTER;/}'{+END});

			window.setTimeout(function() {
				var btn=document.getElementById('attachment_upload_button');
				btn.click();
			},200 /* Longer delay than 0 needed for Firefox */);

			return true;
		}
	//]]></script>
{+END}

{+START,IF,{$NOT,{$BROWSER_MATCHES,simplified_attachments_ui}}}
	{+START,IF,{$ADDON_INSTALLED,filedump}}{+START,IF,{$HAS_ACTUAL_PAGE_ACCESS,filedump}}{+START,IF,{$EQ,{$ZONE},cms}}
		<p>
			{!ADD_ATTACHMENTS_MEDIA_LIBRARY,{POSTING_FIELD_NAME;*}}
		</p>
	{+END}{+END}{+END}

	<script>// <![CDATA[
		var attachment_template='{ATTACHMENT_TEMPLATE;^/}';
	//]]></script>

	<div id="attachment_store">
		{ATTACHMENTS}
	</div>

	{+START,IF,{$JS_ON}}
		<script>// <![CDATA[
			var max_attachments={MAX_ATTACHMENTS%};
			var num_attachments={NUM_ATTACHMENTS%};
		//]]></script>
	{+END}

	{+START,IF_NON_EMPTY,{$_GET,id}}
		<p>
			{!comcode:DELETE_ATTACHMENTS,<a rel="nofollow" title="{!comcode:ATTACHMENT_POPUP} {!LINK_NEW_WINDOW}" target="_blank" href="{$FIND_SCRIPT*,attachment_popup}?field_name={POSTING_FIELD_NAME*}{$KEEP*,0,1}" onclick="window.faux_open(maintain_theme_in_link(this.href)\,'site_attachment_chooser'\,'width=550\,height=600\,status=no\,resizable=yes\,scrollbars=yes'); return false;">{!comcode:ATTACHMENT_POPUP}</a>}
		</p>
	{+END}
{+END}
