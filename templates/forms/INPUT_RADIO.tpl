<div{+START,IF_PASSED,CLASS} class="{CLASS*}"{+END}{+START,IF_PASSED,TABINDEX} tabindex="{TABINDEX*}"{+END}{+START,IF_PASSED,EXTRA} {EXTRA}{+END}>
	{+START,IF_PASSED,LIST}
		{LIST}
	{+END}
	{+START,IF_PASSED,LIST_ARRAY}{+START,IF_PASSED,NAME}
		{+START,LOOP,{LIST_ARRAY}}
			<div>
				<label for="{NAME*}_{_loop_key|*}">
					{+START,IF,{$EQ,{_loop_key},}}
						{+START,IF_NON_PASSED_OR_FALSE,LIST_TRIM_BLANK}
							<input type="radio" id="{NAME*}_{_loop_key|*}" name="{NAME*}" value="{_loop_key*}"{+START,IF_PASSED,VALUE}{+START,IF,{$EQ,{VALUE},{_loop_key}}} checked="checked"{+END}{+END} />
						{+END}
					{+END}

					{+START,IF,{$NEQ,{_loop_key},}}
						<input type="radio" id="{NAME*}_{_loop_key|*}" name="{NAME*}" value="{_loop_key*}"{+START,IF_PASSED,VALUE}{+START,IF,{$EQ,{VALUE},{_loop_key}}} checked="checked"{+END}{+END} />
					{+END}

					{_loop_var*}
				</label>
			</div>
		{+END}
	{+END}{+END}
</div>

{+START,INCLUDE,forms/_INPUT_FIELD}SKIP_LABEL=1{+END}
