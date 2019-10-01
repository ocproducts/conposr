<?php /*

 Conposr Framework (a Composr-lite designed for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    conposr
 */

error_reporting(E_ALL);
set_error_handler('composr_error_handler');
if (function_exists('error_get_last')) {
    register_shutdown_function('catch_fatal_errors');
}

define('UTF8_BOM', "\xEF\xBB\xBF");
define('URL_CONTENT_REGEXP', '\w\-\x80-\xFF'); // PHP is done using ASCII (don't use the 'u' modifier). Note this doesn't include dots, this is intentional as they can cause problems in filenames

require_code('config');
require_code('files');
require_code('tempcode');
require_code('templates');
require_code('temporal');
require_code('urls');
require_code('users');
require_code('web_resources');
require_code('database');

function require_code($codename)
{
    require_once(get_file_base() . '/lib/conposr/' . $codename . '.php');
}

function get_file_base()
{
    global $SITE_INFO;
    if (!empty($SITE_INFO['file_base'])) {
        return $SITE_INFO['file_base'];
    }

    static $file_base = null;
    if ($file_base === null) {
        $file_base = dirname(dirname(__DIR__));
    }
    return $file_base;
}

function filter_naughty($in, $preg = false)
{
    if ((strpos($in, "\0") !== false) && (strpos($in, '..') !== false)) {
        if ($preg) {
            return str_replace('.', '', $in);
        }

        http_response_code(400);
        warn_exit('Invalid URL');
    }

    return $in;
}

function filter_naughty_harsh($in, $preg = false)
{
    if ((function_exists('ctype_alnum')) && (ctype_alnum($in))) {
        return $in;
    }
    if (preg_match('#^[' . URL_CONTENT_REGEXP . ']*$#', $in) !== 0) {
        return $in;
    }

    if ($preg) {
        return preg_replace('#[^' . URL_CONTENT_REGEXP . ']#', '', $in);
    }
    warn_exit('Insecure parameter, ' . $in);
    return ''; // trick to make linters happy
}

function catch_fatal_errors()
{
    $error = error_get_last();

    if ($error !== null) {
        switch ($error['type']) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                composr_error_handler($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }
}

function composr_error_handler($errno, $errstr, $errfile, $errline)
{
    if (((error_reporting() & $errno) !== 0) && (strpos($errstr, 'Illegal length modifier specified')/*Weird random error in dev PHP version*/ === false)) {
        require_code('failure');
        _composr_error_handler($errno, $errstr, $errfile, $errline);
    }

    return false;
}

function either_param_string($name, $default = false)
{
    return get_param_string($name, post_param_string($name, $default));
}

function post_param_string($name, $default = false)
{
    $ret = __param($_POST, $name, $default, false, true);

    if ($ret === null) {
        return null;
    }
    if ((trim($ret) === '') && ($default !== '') && (array_key_exists('require__' . $name, $_POST)) && ($_POST['require__' . $name] !== '0')) {
        if ($default === null) {
            return null;
        }

        http_response_code(400);
        $label = get_param_string('label_for__' . $name, $name);
        warn_exit($label . ' needs to be filled in');
    }

    if ($ret === $default) {
        return $ret;
    }

    require_code('input_filter');
    check_posted_field($name, $ret);
    check_input_field_string($name, $ret, true);

    return $ret;
}

function get_param_string($name, $default = false)
{
    $ret = __param($_GET, $name, $default);

    if (($ret === '') && (isset($_GET['require__' . $name])) && ($default !== $ret) && ($_GET['require__' . $name] !== '0')) {
        http_response_code(400);
        $label = get_param_string('label_for__' . $name, $name);
        warn_exit($label . ' needs to be filled in');
    }

    if ($ret === $default) {
        return $ret;
    }

    require_code('input_filter');
    check_input_field_string($name, $ret);

    return $ret;
}

function __param($array, $name, $default, $integer = false, $posted = false)
{
    if ((!isset($array[$name])) || ($array[$name] === false) || (($integer) && ($array[$name] === ''))) {
        if ($default !== false) {
            return $default;
        }

        http_response_code(400);
        $label = get_param_string('label_for__' . $name, $name);
        warn_exit($label . ' missing');
    }

    $val = $array[$name];
    if (is_array($val)) {
        $val = trim(implode(',', $val), ' ,');
    }

    return $val;
}

function either_param_integer($name, $default = false)
{
    return get_param_integer($name, post_param_integer($name, $default));
}

function post_param_integer($name, $default = false)
{
    $ret = __param($_POST, $name, ($default === false) ? $default : (($default === null) ? '' : strval($default)), true, true);

    if (((($default === null) && ($ret === '')) ? null : intval($ret)) !== $default) {
        require_code('input_filter');
        check_posted_field($name, $ret);
    }

    if (($default === null) && ($ret === '')) {
        return null;
    }
    if (!is_numeric($ret)) {
        http_response_code(400);
        $label = get_param_string('label_for__' . $name, $name);
        warn_exit($label . ' must be a number');
    }
    if ($ret === '0') {
        return 0;
    }
    if ($ret === '1') {
        return 1;
    }
    $reti = intval($ret);
    $retf = floatval($reti);
    if (($retf > 2147483647.0) || ($retf < -2147483648.0)) {
        http_response_code(400);
        $label = get_param_string('label_for__' . $name, $name);
        warn_exit($label . ' is too small/big');
    }
    return $reti;
}

function get_param_integer($name, $default = false)
{
    $m_default = ($default === false) ? false : (isset($default) ? (($default === 0) ? '0' : strval($default)) : '');
    $ret = __param($_GET, $name, $m_default, true);
    if ((!isset($default)) && ($ret === '')) {
        return null;
    }
    if (!is_numeric($ret)) {
        if (substr($ret, -1) === '/') {
            $ret = substr($ret, 0, strlen($ret) - 1);
        }
    }
    if ($ret === '0') {
        return 0;
    }
    if ($ret === '1') {
        return 1;
    }
    $reti = intval($ret);
    $retf = floatval($reti);
    if (($retf > 2147483647.0) || ($retf < -2147483648.0)) {
        http_response_code(400);
        $label = get_param_string('label_for__' . $name, $name);
        warn_exit($label . ' is too small/big');
    }
    return $reti;
}

function float_to_raw_string($num, $decs_wanted = 2, $only_needed_decs = false)
{
    $str = number_format($num, $decs_wanted, '.', '');
    $dot_pos = strpos($str, '.');
    $decs_here = ($dot_pos === false) ? 0 : (strlen($str) - $dot_pos - 1);
    if ($decs_here < $decs_wanted) {
        for ($i = 0; $i < $decs_wanted - $decs_here; $i++) {
            $str .= '0';
        }
    } elseif ($decs_here > $decs_wanted) {
        $str = substr($str, 0, strlen($str) - $decs_here + $decs_wanted);
        if ($decs_wanted == 0 && !$only_needed_decs) {
            $str = rtrim($str, '.');
        }
    }
    if ($only_needed_decs && $decs_wanted != 0) {
        $str = rtrim(rtrim($str, '0'), '.');
    }
    return $str;
}

function float_format($val, $decs_wanted = 2, $only_needed_decs = false)
{
    $locale = localeconv();
    if ($locale['thousands_sep'] == '') {
        $locale['thousands_sep'] = ',';
    }
    $str = number_format($val, $decs_wanted, $locale['decimal_point'], $locale['thousands_sep']);
    $dot_pos = strpos($str, '.');
    $decs_here = ($dot_pos === false) ? 0 : (strlen($str) - $dot_pos - 1);
    if ($decs_here < $decs_wanted) {
        for ($i = 0; $i < $decs_wanted - $decs_here; $i++) {
            $str .= '0';
        }
    } elseif ($decs_here > $decs_wanted) {
        $str = substr($str, 0, strlen($str) - $decs_here + $decs_wanted);
        if ($decs_wanted == 0) {
            $str = rtrim($str, '.');
        }
    }
    if ($only_needed_decs && $decs_wanted != 0) {
        $str = rtrim(rtrim($str, '0'), '.');
    }
    return $str;
}

function float_unformat($str, $no_thousands_sep = false)
{
    $locale = localeconv();

    // Simplest case?
    if (preg_match('#^\d+$#', $str) != 0) { // E.g. "123"
        return floatval($str);
    }

    if ($no_thousands_sep) {
        // We can assume a "." is a decimal point then?
        if (preg_match('#^\d+\.\d+$#', $str) != 0) { // E.g. "123.456"
            return floatval($str);
        }
    }

    // Looks like English-format? It couldn't be anything else because thousands_sep always comes before decimal_point
    if (preg_match('#^[\d,]+\.\d+$#', $str) != 0) { // E.g. "123,456.789"
        return floatval($str);
    }

    // Now it must e E.g. "123.456,789" or "123.456", or something from another language which uses other separators...

    if ($locale['thousands_sep'] != '') {
        $str = str_replace($locale['thousands_sep'], '', $str);
    }
    $str = str_replace($locale['decimal_point'], '.', $str);
    return floatval($str);
}

function integer_format($val)
{
    static $locale = null;
    if ($locale === null) {
        $locale = localeconv();
        if ($locale['thousands_sep'] == '') {
            $locale['thousands_sep'] = ',';
        }
    }
    return number_format($val, 0, $locale['decimal_point'], $locale['thousands_sep']);
}

function sort_maps_by(&$rows, $sort_keys, $preserve_order_if_possible = false)
{
    if ($rows == array()) {
        return;
    }

    global $M_SORT_KEY;
    $M_SORT_KEY = $sort_keys;
    if ($preserve_order_if_possible) {
        merge_sort($rows, '_multi_sort');
    } else {
        $first_key = key($rows);
        if ((is_integer($first_key)) && (array_unique(array_map('is_integer', array_keys($rows))) === array(true))) {
            usort($rows, '_multi_sort');
        } else {
            uasort($rows, '_multi_sort');
        }
    }
}

function merge_sort(&$array, $cmp_function = 'strcmp')
{
    // Arrays of size<2 require no action.
    if (count($array) < 2) {
        return;
    }

    // Split the array in half
    $halfway = intval(floatval(count($array)) / 2.0);
    $array1 = array_slice($array, 0, $halfway);
    $array2 = array_slice($array, $halfway);

    // Recurse to sort the two halves
    merge_sort($array1, $cmp_function);
    merge_sort($array2, $cmp_function);

    // If all of $array1 is <= all of $array2, just append them.
    if (call_user_func($cmp_function, end($array1), reset($array2)) < 1) {
        $array = array_merge($array1, $array2);
        return;
    }

    // Merge the two sorted arrays into a single sorted array
    $array = array();
    reset($array1);
    reset($array2);
    $ptr1 = 0;
    $ptr2 = 0;
    $cnt1 = count($array1);
    $cnt2 = count($array2);
    while (($ptr1 < $cnt1) && ($ptr2 < $cnt2)) {
        if (call_user_func($cmp_function, current($array1), current($array2)) < 1) {
            $key = key($array1);
            if (is_integer($key)) {
                $array[] = current($array1);
            } else {
                $array[$key] = current($array1);
            }
            $ptr1++;
            next($array1);
        } else {
            $key = key($array2);
            if (is_integer($key)) {
                $array[] = current($array2);
            } else {
                $array[$key] = current($array2);
            }
            $ptr2++;
            next($array2);
        }
    }

    // Merge the remainder
    while ($ptr1 < $cnt1) {
        $key = key($array1);
        if (is_integer($key)) {
            $array[] = current($array1);
        } else {
            $array[$key] = current($array1);
        }
        $ptr1++;
        next($array1);
    }
    while ($ptr2 < $cnt2) {
        $key = key($array2);
        if (is_integer($key)) {
            $array[] = current($array2);
        } else {
            $array[$key] = current($array2);
        }
        $ptr2++;
        next($array2);
    }
}

function _multi_sort($a, $b)
{
    global $M_SORT_KEY;
    $keys = explode(',', is_string($M_SORT_KEY) ? $M_SORT_KEY : strval($M_SORT_KEY));
    $first_key = $keys[0];
    if ($first_key[0] === '!') {
        $first_key = substr($first_key, 1);
    }

    if ((is_string($a[$first_key])) || (is_object($a[$first_key]))) {
        $ret = 0;
        do {
            $key = array_shift($keys);

            $backwards = ($key[0] === '!');
            if ($backwards) {
                $key = substr($key, 1);
            }

            $av = $a[$key];
            $bv = $b[$key];

            if (is_object($av)) {
                $av = $av->evaluate();
            }
            if (is_object($bv)) {
                $bv = $bv->evaluate();
            }

            if ($backwards) { // Flip around
                if ((is_numeric($av)) && (is_numeric($bv))) {
                    $ret = -strnatcasecmp($av, $bv);
                } else {
                    $ret = -strcasecmp($av, $bv);
                }
            } else {
                if ((is_numeric($av)) && (is_numeric($bv))) {
                    $ret = strnatcasecmp($av, $bv);
                } else {
                    $ret = strcasecmp($av, $bv);
                }
            }
        } while ((count($keys) !== 0) && ($ret === 0));
        return $ret;
    }

    do {
        $key = array_shift($keys);
        if ($key[0] === '!') { // Flip around
            $key = substr($key, 1);
            $ret = ($a[$key] > $b[$key]) ? -1 : (($a[$key] == $b[$key]) ? 0 : 1);
        } else {
            $ret = ($a[$key] > $b[$key]) ? 1 : (($a[$key] == $b[$key]) ? 0 : -1);
        }
    } while ((count($keys) !== 0) && ($ret == 0));
    return $ret;
}

function fix_id($param)
{
    if (preg_match('#^[A-Za-z][\w]*$#', $param) !== 0) {
        return $param; // Optimisation
    }

    $length = strlen($param);
    $new = '';
    for ($i = 0; $i < $length; $i++) {
        $char = $param[$i];
        switch ($char) {
            case '[':
                $new .= '_opensquare_';
                break;
            case ']':
                $new .= '_closesquare_';
                break;
            case '&#039;':
            case '\'':
                $new .= '_apostophe_';
                break;
            case '-':
                $new .= '_minus_';
                break;
            case ' ':
                $new .= '_space_';
                break;
            case '+':
                $new .= '_plus_';
                break;
            case '*':
                $new .= '_star_';
                break;
            case '/':
                $new .= '__';
                break;
            default:
                $ascii = ord($char);
                if ((($i !== 0) && ($char === '_')) || (($ascii >= 48) && ($ascii <= 57)) || (($ascii >= 65) && ($ascii <= 90)) || (($ascii >= 97) && ($ascii <= 122))) {
                    $new .= $char;
                } else {
                    $new .= '_' . strval($ascii) . '_';
                }
                break;
        }
    }
    if ($new === '') {
        $new = 'zero_length';
    }
    if ($new[0] === '_') {
        $new = 'und_' . $new;
    }
    return $new;
}

function list_to_map($map_value, $list)
{
    $i = 0;

    $new_map = array();

    foreach ($list as $map) {
        $key = $map[$map_value];
        $new_map[$key] = $map;

        $i++;
    }

    if ($i > 0) {
        return $new_map;
    }
    return array();
}

function collapse_2d_complexity($key, $value, $list)
{
    $new_map = array();
    foreach ($list as $map) {
        $new_map[$map[$key]] = $map[$value];
    }

    return $new_map;
}

function collapse_1d_complexity($key, $list)
{
    $new_array = array();
    foreach ($list as $map) {
        if ($key === null) {
            $new_array[] = array_shift($map);
        } else {
            $new_array[] = $map[$key];
        }
    }

    return $new_array;
}

function get_ip_address()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
}

function log_it($message)
{
    $admin_log = get_option('admin_log');
    if ($admin_log !== null) {
        $myfile = fopen($admin_log, 'ab');
        flock($myfile, LOCK_EX);
        fwrite($myfile, $message . "\n");
        flock($myfile, LOCK_UN);
        fclose($myfile);
    }
}

function escape_html($string)
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'utf-8');
}

function is_mobile()
{
    static $is_mobile = null;
    if ($is_mobile !== null) {
        return $is_mobile;
    }

    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    // The set of browsers
    $browsers = array(
        // Implication by technology claims
        'WML',
        'WAP',
        'Wap',
        'MIDP', // Mobile Information Device Profile

        // Generics
        'Mobile',
        'Smartphone',
        'WebTV',

        // Well known/important browsers/brands
        'Mobile Safari', // Usually Android
        'Android',
        'iPhone',
        'iPod',
        'Opera Mobi',
        'Opera Mini',
        'BlackBerry',
        'Windows Phone',
        'nook browser', // Barnes and Noble
    );

    $exceptions = array(
        'iPad',
    );

    // The test
    $is_mobile = (preg_match('/(' . implode('|', $browsers) . ')/i', $user_agent) != 0) && (preg_match('/(' . implode('|', $exceptions) . ')/i', $user_agent) == 0);

    return $is_mobile;
}

function get_bot_type($agent = null)
{
    static $bot_type = null;
    static $done_detect = false;
    if ($done_detect) {
        return $bot_type;
    }
    $done_detect = true;

    if ($agent === null) {
        $agent = $_SERVER['HTTP_USER_AGENT'];
    }

    $agent = strtolower($agent);

    $bots = array(
        'zyborg' => 'Looksmart',
        'googlebot' => 'Google',
        'mediapartners-google' => 'Google Adsense',
        'teoma' => 'Teoma',
        'jeeves' => 'Ask Jeeves',
        'ultraseek' => 'Infoseek',
        'ia_archiver' => 'Alexa/Archive.org',
        'msnbot' => 'Bing',
        'bingbot' => 'Bing',
        'mantraagent' => 'LookSmart',
        'wisenutbot' => 'Looksmart',
        'paros' => 'Paros',
        'sqworm' => 'Aol.com',
        'baidu' => 'Baidu',
        'facebookexternalhit' => 'Facebook',
        'yandex'=> 'Yandex',
        'daum' => 'Daum',
        'ahrefsbot' => 'Ahrefs',
        'mj12bot' => 'Majestic-12',
        'blexbot' => 'webmeup',
        'duckduckbot' => 'DuckDuckGo',
    );

    foreach ($bots as $id => $name) {
        if (strpos($agent, $id) !== false) {
            $bot_type = $name;
            return $name;
        }
    }

    if ((strpos($agent, 'bot') !== false) || (strpos($agent, 'spider') !== false)) {
        $to_a = strpos($agent, ' ');
        if ($to_a === false) {
            $to_a = strlen($agent);
        }
        $to_b = strpos($agent, '/');
        if ($to_b === false) {
            $to_b = strlen($agent);
        }

        $name = substr($agent, 0, min($to_a, $to_b));

        $bot_type = $name;
        return $name;
    }

    $bot_type = null;
    return null;
}

function strip_html($in)
{
    if ((strpos($in, '<') === false) && (strpos($in, '&') === false)) {
        return $in; // Optimisation
    }

    $search = array(
        '#<script[^>]*?' . '>.*?</script>#si',  // Strip out JavaScript
        '#<style[^>]*?' . '>.*?</style>#siU',   // Strip style tags properly
        '#<![\s\S]*?--[ \t\n\r]*>#',            // Strip multi-line comments including CDATA
    );
    $in = preg_replace($search, '', $in);
    $in = str_replace('><', '> <', $in);
    $in = strip_tags($in);
    return html_entity_decode($in, ENT_QUOTES);
}

function is_email_address($string)
{
    if ($string == '') {
        return false;
    }

    return (preg_match('#^[\w\.\-\+]+@[\w\.\-]+$#', $string) != 0); // Put "\.[a-zA-Z0-9_\-]+" before $ to ensure a two+ part domain
}

function cms_ob_end_clean()
{
    while (ob_get_level() > 0) {
        if (!@ob_end_clean()) {
            @ini_set('zlib.output_compression', '0');
            break;
        }
    }
}

/**
 * Check if a string starts with a substring.
 *
 * @param  string $haystack The haystack
 * @param  string $needle The needle
 * @return boolean Whether the haystack starts with the needle
 */
function starts_with($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

/**
 * Check if a string ends with a substring.
 *
 * @param  string $haystack The haystack
 * @param  string $needle The needle
 * @return boolean Whether the haystack ends with the needle
 */
function ends_with($haystack, $needle)
{
    $length = strlen($needle);
    return ($length === 0) || (substr($haystack, -$length) === $needle);
}

function cms_mb_strlen($in)
{
    if (function_exists('mb_strlen')) {
        return @mb_strlen($in, 'utf-8'); // @ is because there could be invalid unicode involved
    }
    if (function_exists('iconv_strlen')) {
        return @iconv_strlen($in, 'utf-8');
    }
    return strlen($in);
}

function cms_mb_substr($in, $from, $amount = null)
{
    if ($amount === null) {
        $amount = cms_mb_strlen($in) - $from;
    }

    if ($in == '' || strlen($in) == $from)
    {
        return ''; // Workaround PHP bug/inconsistency (https://bugs.php.net/bug.php?id=72320)
    }

    if (function_exists('iconv_substr')) {
        return @iconv_substr($in, $from, $amount, 'utf-8');
    }
    if (function_exists('mb_substr')) {
        return @mb_substr($in, $from, $amount, 'utf-8');
    }

    $ret = substr($in, $from, $amount);
    $end = ord(substr($ret, -1));
    if (($end >= 192) && ($end <= 223)) {
        $ret .= substr($in, $from + $amount, 1);
    }
    if ($from != 0) {
        $start = ord(substr($ret, 0, 1));
        if (($start >= 192) && ($start <= 223)) {
            $ret = substr($in, $from - 1, 1) . $ret;
        }
    }
    return $ret;
}

function cms_mb_ucwords($in)
{
    if (function_exists('mb_convert_case')) {
        return @mb_convert_case($in, MB_CASE_TITLE);
    }

    return ucwords($in);
}

function cms_mb_strtolower($in)
{
    if (function_exists('mb_strtolower')) {
        return @mb_strtolower($in);
    }

    return strtolower($in);
}

function cms_mb_strtoupper($in)
{
    if (function_exists('mb_strtoupper')) {
        return @mb_strtoupper($in);
    }

    return strtoupper($in);
}

/**
 * Make sure that lines are separated by "\n", with no "\r"'s there at all. For Mac data, this will be a flip scenario. For Linux data this will be a null operation. For windows data this will be change from "\r\n" to just "\n". For a realistic scenario, data could have originated on all kinds of platforms, with some editors converting, some situations being inter-platform, and general confusion. Don't make blind assumptions - use this function to clean data, then write clean code that only considers "\n"'s.
 *
 * @param  string $in The data to clean
 * @return string The cleaned data
 */
function unixify_line_format($in)
{
    if ($in === '') {
        return $in;
    }

    if (substr($in, 0, 3) === UTF8_BOM) {
        $in = substr($in, 3);
    }

    static $from = null;
    if ($from === null) {
        $from = array("\r\n", "\r");
    }
    static $to = null;
    if ($to === null) {
        $to = array("\n", "\n");
    }
    $in = str_replace($from, $to, $in);
    return $in;
}

// Conposr-specific...

function convert_camelcase_to_underscore($key)
{
    $_key = '';
    $len = strlen($key);
    for ($i = 0; $i < $len; $i++) {
        $c = $key[$i];
        if (ctype_upper($c)) {
            if ($i != 0) {
                $_key .= '_';
            }
            $_key .= strtolower($c);
        } else {
            $_key .= $c;
        }
    }
    return $_key;
}

function convert_underscore_to_camelcase($key)
{
    $_key = '';
    $len = strlen($key);
    $previousUnderscore = false;
    for ($i = 0; $i < $len; $i++) {
        $c = $key[$i];
        if ($c == '_') {
            $previousUnderscore = true;
        } else {
            if ($previousUnderscore) {
                $_key .= strtoupper($c);
                $previousUnderscore = false;
            } else {
                $_key .= $c;
            }
        }
    }
    return $_key;
}
