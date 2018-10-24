<input type="date" id="{NAME*}" name="{NAME*}" value="{VALUE*}"{+START,IF_PASSED_AND_TRUE,REQUIRED} required="required"{+END}{+START,IF_PASSED,MIN} min="{MIN*}"{+END}{+START,IF_PASSED,MIN} max="{MAX*}"{+END} />

{+START,INCLUDE,_INPUT_FIELD}{+END}
