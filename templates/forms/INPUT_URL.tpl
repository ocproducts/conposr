<input type="url"{+START_IF_PASSED,ID} id="{ID*}"{+END}{+START_IF_NON_PASSED,ID} id="{NAME*}"{+END} name="{NAME*}" value="{VALUE*}"{+START,IF_PASSED_AND_TRUE,REQUIRED} required="required"{+END}{+START,IF_PASSED,PLACEHOLDER} placeholder="{PLACEHOLDER*}"{+END}{+START,IF_PASSED,MAXLENGTH} maxlength="{MAXLENGTH*}"{+END}{+START,IF_PASSED,SIZE} size="{SIZE*}"{+END}{+START,IF_PASSED,CLASS} class="{CLASS*}"{+END}{+START,IF_PASSED_AND_TRUE,DISABLED} disabed="disabled"{+END}{+START,IF_PASSED_AND_TRUE,READONLY} readonly="readonly"{+END}{+START,IF_PASSED,TABINDEX} tabindex="{TABINDEX*}"{+END}{+START,IF_PASSED,EXTRA} {EXTRA}{+END} />

{+START,INCLUDE,forms/_INPUT_FIELD}{+END}
