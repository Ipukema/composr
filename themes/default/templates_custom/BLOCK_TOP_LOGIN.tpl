{+START,IF,{$NOR,{$GET,login_screen},{$MATCH_KEY_MATCH,_WILD:login}}}
	{+START,INCLUDE,BLOCK_TOP_LOGIN}{+END}

	{+START,IF_NON_EMPTY,{$CONFIG_OPTION,facebook_appid}}{+START,IF,{$CONFIG_OPTION,facebook_allow_signups}}
		{+START,IF_EMPTY,{$FB_CONNECT_UID}}
			<div class="fb-login-button" data-scope="email,user_birthday,user_about_me,user_hometown,user_location,user_website{+START,IF,{$CONFIG_OPTION,facebook_auto_syndicate}},publish_actions{+END}"></div>
		{+END}
	{+END}{+END}
{+END}