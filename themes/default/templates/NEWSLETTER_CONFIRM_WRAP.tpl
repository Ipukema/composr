<div class="box box___newsletter_confirm_wrap"><div class="box_inner">
	<h2>{SUBJECT*} &ndash; {!HTML_VERSION}</h2>

	{+START,INCLUDE,NEWSLETTER_PREVIEW}{+END}
</div></div>

{+START,IF_NON_EMPTY,{TEXT_PREVIEW}}
	<div class="box box___newsletter_confirm_wrap"><div class="box_inner">
		<h2>{SUBJECT*} &ndash; {!TEXT_VERSION}</h2>

		<div class="whitespace_visible">{TEXT_PREVIEW*}</div>
	</div></div>
{+END}

{+START,IF_PASSED,SPAM_REPORT}
	{+START,IF_PASSED,SPAM_SCORE}
		<div class="box box___newsletter_confirm_wrap"><div class="box_inner">
			<h2>{!SPAM_SCORE,{SPAM_SCORE*}}</h2>

			<div class="whitespace_visible">{SPAM_REPORT*}</div>
		</div></div>
	{+END}
{+END}

<p>
	{!NEWSLETTER_CONFIRM_MESSAGE}
</p>
