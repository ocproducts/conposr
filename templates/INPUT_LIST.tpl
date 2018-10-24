<select name="{NAME*}"{+START,IF_PASSED_AND_TRUE,REQUIRED} required="required"{+END}{+START,IF_PASSED_AND_TRUE,MULTIPLE} multiple="multiple"{+END}{+START,IF_PASSED,SIZE} size="{SIZE*}"{+END}>
	{LIST}
</select>

{+START,INCLUDE,_INPUT_FIELD}{+END}
