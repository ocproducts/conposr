<select{+START,IF_PASSED,ID} id="{ID*}"{+END}{+START,IF_NON_PASSED,ID} id="{NAME*}"{+END} name="{NAME*}"{+START,IF_PASSED_AND_TRUE,REQUIRED} required="required"{+END}{+START,IF_PASSED_AND_TRUE,MULTIPLE} multiple="multiple"{+END}{+START,IF_PASSED,SIZE} size="{SIZE*}"{+END}{+START,IF_PASSED,CLASS} class="{CLASS*}"{+END}{+START,IF_PASSED_AND_TRUE,DISABLED} disabled="disabled"{+END}{+START,IF_PASSED,TABINDEX} tabindex="{TABINDEX*}"{+END}{+START,IF_PASSED,EXTRA} {EXTRA}{+END}>
	{+START,IF_PASSED,LIST}
		{LIST}
	{+END}
	{+START,IF_PASSED,LIST_ARRAY}
		{+START,LOOP,{LIST_ARRAY}}
			{+START,IF,{$EQ,{_loop_key},}}
				{+START,IF_NON_PASSED_OR_FALSE,LIST_TRIM_BLANK}
					<option value="{_loop_key*}"{+START,IF_PASSED,VALUE}{+START,IF,{$EQ,{VALUE},{_loop_key}}} selected="selected"{+END}{+END}>{$REPLACE*,&#44;,\,,{_loop_var}}</option>
				{+END}
			{+END}

			{+START,IF,{$NEQ,{_loop_key},}}
				<option value="{_loop_key*}"{+START,IF_PASSED,VALUE}{+START,IF,{$EQ,{VALUE},{_loop_key}}} selected="selected"{+END}{+END}>{$REPLACE*,&#44;,\,,{_loop_var}}</option>
			{+END}
		{+END}
	{+END}
</select>

{+START,INCLUDE,forms/_INPUT_FIELD}{+END}
