{+START,IF_PASSED,PRETTY_NAME}
	<input type="hidden" name="label_for__{NAME*}" value="{$STRIP_HTML,{PRETTY_NAME*}}" />
{+END}

{+START,IF_PASSED_AND_TRUE,REQUIRED}
	<input type="hidden" name="require__{NAME*}" value="1" />
{+END}
