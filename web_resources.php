<?php /*

 Conposr Framework (a Composr-lite designed for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    conposr
 */

global $CSS_REQUIRED, $JAVASCRIPT_REQUIRED;
$CSS_REQUIRED = array();
$JAVASCRIPT_REQUIRED = array();

function require_css($css)
{
    global $CSS_REQUIRED;
    $CSS_REQUIRED[] = $css;
}

function require_javascript($js)
{
    global $JAVASCRIPT_REQUIRED;
    $JAVASCRIPT_REQUIRED[] = $js;
}
