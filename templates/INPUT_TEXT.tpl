<textarea id="{NAME*}" name="{NAME*}"{+START,IF_PASSED_AND_TRUE,REQUIRED} required="required"{+END}{+START,IF_PASSED,PLACEHOLDER} placeholder="{PLACEHOLDER*}"{+END}{+START,IF_PASSED,MAXLENGTH} maxlength="{MAXLENGTH*}"{+END}{+START,IF_PASSED,ROWS} rows="{ROWS*}"{+END}{+START,IF_NON_PASSED,ROWS} rows="10"{+END}>{VALUE*}</textarea>

{+START,INCLUDE,_INPUT_FIELD}{+END}