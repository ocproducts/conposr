<?php /*

 Conposr (Composr-lite framework for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    Conposr
 */

function globalise($title, $middle)
{
    return do_template('GLOBAL_HTML_WRAP', array('TITLE' => $title, 'MIDDLE' => $middle));
}

function inform_exit($text)
{
    require_code('failure');
    _generic_exit($text, 'INFORM_SCREEN');
}

function warn_exit($text)
{
    require_code('failure');
    _generic_exit($text, 'WARN_SCREEN');
}

function fatal_exit($text)
{
    require_code('failure');
    _fatal_exit($text);
}

function form_input_hidden($name, $value)
{
    return '<input type="hidden" name="' . escape_html($name) . '" value="' . escape_html($value) . '" />';
}

function form_input_list_entry($value, $selected, $text)
{
    return '<option value="' . escape_html($value) . '"' . ($selected ? ' selected="selected"' : '') . '>' . escape_html($text) . '</option>';
}

function form_input_radio_entry($name, $value, $selected = false, $text = '')
{
    if ($text == '') {
        $text = $value;
    }

    return '<input type="radio" name="' . escape_html($name) . '" value="' . escape_html($value) . '"' . ($selected ? ' checked="checked"' : '') . '>' . escape_html($text) . '</option>';
}
