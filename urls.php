<?php /*

 Conposr Framework (a Composr-lite designed for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    conposr
 */

function tacit_https()
{
    static $tacit_https = null;
    if ($tacit_https === null) {
        $tacit_https = ((!empty($_SERVER['HTTPS'])) && ($_SERVER['HTTPS'] != 'off')) || ((array_key_exists('HTTP_X_FORWARDED_PROTO', $_SERVER)) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'));
    }
    return $tacit_https;
}

function get_base_url()
{
    $base_url = get_option('base_url');
    if ($base_url === null) {
        $protocol = tacit_https() ? 'https' : 'http';
        $base_url = $protocol . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
    }
    return $base_url;
}

function get_self_url_easy()
{
    $protocol = tacit_https() ? 'https' : 'http';
    $self_url = $protocol . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
    if (isset($_SERVER['REQUEST_URI'])) {
        $self_url .= $_SERVER['REQUEST_URI'];
    }
    return $self_url;
}

function build_url($script, $vars = null)
{
    if (empty($vars)) {
        return get_base_url() . '/' . $script;
    }
    return get_base_url() . '/' . $script . '?' . http_build_query($vars);
}
