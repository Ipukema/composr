{$REQUIRE_JAVASCRIPT,profile}
{$REQUIRE_JAVASCRIPT,editing}
{$REQUIRE_CSS,cns}
<div class="box" data-require-javascript="password_censor" data-tpl="comcodeEncrypt"><div class="box_inner">
	<h3>Encrypted text</h3>

	{+START,IF_NON_EMPTY,{$_POST,decrypt}}
		{$DECRYPT,{CONTENT},{$_POST,decrypt}}
	{+END}

	{+START,IF_EMPTY,{$_POST,decrypt}}
		<p>
			<a href="#!" class="js-click-decrypt-data" title="{!encryption:DECRYPT_DATA}: {!encryption:DESCRIPTION_DECRYPT_DATA}">{!encryption:DECRYPT_DATA}</a>
		</p>
	{+END}
</div></div>
