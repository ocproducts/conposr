<?php /*

 Conposr (Composr-lite framework for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    Conposr
 */

date_default_timezone_set(get_option('timezone'));

function get_timezoned_date($timestamp, $include_time = true, $use_contextual_dates = false)
{
    $date = date(get_option('date_format', $timestamp));

    $ret = $date;

    if ($include_time) {
        $time = date(get_option('time_format', $timestamp));
        $ret .= ' ' . $time;
    }

    return $ret;
}