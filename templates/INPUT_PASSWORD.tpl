<input type="password" id="{NAME*}" name="{NAME*}" value="{VALUE*}"{+START,IF_PASSED_AND_TRUE,REQUIRED} required="required"{+END}{+START,IF_PASSED,PLACEHOLDER} placeholder="{PLACEHOLDER*}"{+START,IF_PASSED,MAXLENGTH} maxlength="{MAXLENGTH*}"{+END}{+START,IF_PASSED,SIZE} size="{SIZE*}"{+END} />

{+START,INCLUDE,_INPUT_FIELD}{+END}
