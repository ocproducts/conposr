<input type="hidden" name="{NAME*}" value="0" />
<input type="checkbox"{+START,IF_PASSED,ID} id="{ID*}"{+END}{+START,IF_NON_PASSED,ID} id="{NAME*}"{+END} name="{NAME*}" value="1"{+START,IF_PASSED_AND_TRUE,TICKED} checked="checked"{+END}{+START,IF_PASSED,CLASS} class="{CLASS*}"{+END}{+START,IF_PASSED_AND_TRUE,DISABLED} disabled="disabled"{+END}{+START,IF_PASSED_AND_TRUE,READONLY} readonly="readonly"{+END}{+START,IF_PASSED,TABINDEX} tabindex="{TABINDEX*}"{+END}{+START,IF_PASSED,EXTRA} {EXTRA}{+END} />

{+START,INCLUDE,forms/_INPUT_FIELD}REQUIRED=0{+END}
