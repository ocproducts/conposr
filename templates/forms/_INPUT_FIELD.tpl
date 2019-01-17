{+START,IF_PASSED,PRETTY_NAME}
	<input type="hidden" name="label_for__{NAME*}" value="{$STRIP_HTML,{PRETTY_NAME*}}" />
{+END}

{+START,IF_PASSED_AND_TRUE,REQUIRED}
	<input type="hidden" name="require__{NAME*}" value="1" />
{+END}

{+START,IF_NON_PASSED_OR_FALSE,SKIP_LABEL}
	<div id="{+START,IF_PASSED,ID}{ID*}{+END}{+START,IF_PASSED,NAME}{NAME*}{+END}Error" style="display: none" class="inlineErrorLabel"></div>
{+END}
