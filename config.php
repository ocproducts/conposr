<?php /*

 Conposr Framework (a Composr-lite designed for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    conposr
 */

global $CONFIG_OPTIONS;
if (!isset($CONFIG_OPTIONS)) {
    $CONFIG_OPTIONS = array();
}
$CONFIG_OPTIONS += array(
    'date_format' => 'd/m/Y',
    'time_format' => 'H:i:s',
    'timezone' => date_default_timezone_get(),

    'dev_ip' => '127.0.0.1',

    'error_log' => get_file_base() . '/conposr/error.log',
    'admin_log' => get_file_base() . '/conposr/admin.log',

    'db_type' => 'mysqli',
    'db_site_host' => 'localhost',
    'db_site' => null,
    'db_site_user' => 'root',
    'db_site_password' => '',
    'table_prefix' => '',

    'base_url' => null,
);

$error_log = get_option('error_log');
if ($error_log !== null) {
    ini_set('log_errors', '1');
    ini_set('display_errors', '0');
    ini_set('error_log', $error_log);
}
ini_set('html_errors', '0');

function get_option($name)
{
    global $CONFIG_OPTIONS;
    if (isset($CONFIG_OPTIONS[$name])) {
        return $CONFIG_OPTIONS[$name];
    }
    return null;
}
