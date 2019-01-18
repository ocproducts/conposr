<?php /*

 Conposr Framework (a Composr-lite designed for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    conposr
 */

function check_input_field_string($name, &$val, $posted = false)
{
    if (preg_match('#^\w*$#', $val) !== 0) {
        return;
    }

    // Security for URL context (not only things that are specifically known URL parameters)
    if ((preg_match('#^\s*((((j\s*a\s*v\s*a\s*)|(v\s*b\s*))?s\s*c\s*r\s*i\s*p\s*t)|(d\s*a\s*t\s*a))\s*:#i', $val) !== 0) && ($name !== 'value')/*Don't want autosave triggering this*/) {
        $label = get_param_string('label_for__' . $name, $name);
        warn_exit('Value of ' . $label . ' looks suspicious');
    }

    // Security check for known URL fields. Check for specific things, plus we know we can be pickier in general
    $is_url = ($name === 'from') || ($name === 'preview_url') || ($name === 'redirect') || ($name === 'redirect_passon') || ($name === 'url');
    if ($is_url) {
        if (preg_match('#\n|\000|<|(".*[=<>])|^\s*((((j\s*a\s*v\s*a\s*)|(v\s*b\s*))?s\s*c\s*r\s*i\s*p\s*t)|(d\s*a\s*t\s*a\s*))\s*:#mi', $val) !== 0) {
            if ($name === 'page') { // Stop loops
                $_GET[$name] = '';
            }
            $label = get_param_string('label_for__' . $name, $name);
            warn_exit('Value of ' . $label . ' looks suspicious');
        }
    }
}

function check_posted_field($name, $val)
{
    $referer = $_SERVER['HTTP_REFERER'];
    if ($referer == '') {
        $referer = $_SERVER['HTTP_ORIGIN'];
    }

    $is_true_referer = (substr($referer, 0, 7) === 'http://') || (substr($referer, 0, 8) === 'https://');

    if (($_SERVER['REQUEST_METHOD'] === 'POST') && (!is_guest())) {
        if ($is_true_referer) {
            $canonical_referer_domain = strip_url_to_representative_domain($referer);
            $canonical_baseurl_domain = strip_url_to_representative_domain(get_base_url());
            if ($canonical_referer_domain != $canonical_baseurl_domain) {
                if (count($_POST) != 0) {
                    warn_exit('URL POST came from external domain');
                }
            }
        }
    }
}

function strip_url_to_representative_domain($url)
{
    return preg_replace('#^www\.#', '', strtolower(parse_url($url, PHP_URL_HOST)));
}
