<?php /*

 Conposr Framework (a Composr-lite designed for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    conposr
 */

global $THROWING_ERRORS;
$THROWING_ERRORS = false;

function _composr_error_handler($errno, $errstr, $errfile, $errline)
{
    // Strip down path for security
    if (substr(str_replace(DIRECTORY_SEPARATOR, '/', $errfile), 0, strlen(get_file_base() . '/')) == str_replace(DIRECTORY_SEPARATOR, '/', get_file_base() . '/')) {
        $errfile = substr($errfile, strlen(get_file_base() . '/'));
    }

    // Work out the error type
    switch ($errno) {
        case E_RECOVERABLE_ERROR: // constant not defined in all php versions but we defined it
        case E_USER_ERROR:
        case E_PARSE:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_ERROR:
            $type = 'error';
            break;
        case -123: // Hacked in for the memtrack extension, which was buggy
        case E_CORE_WARNING:
        case E_COMPILE_WARNING:
        case E_USER_WARNING:
        case E_WARNING:
            $type = 'warning';
            break;
        case E_USER_NOTICE:
        case E_NOTICE:
            $type = 'notice';
            break;
        case E_STRICT:
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
        default: // We don't know the error type, or we know it's incredibly minor, so it's probably best to continue - PHP will output it for staff or if display_php_errors is on
            return false;
    }

    $errstr = _sanitise_error_msg($errstr);

    // Put into error log
    if ((get_param_integer('keep_fatalistic', 0) == 0) && (!throwing_errors())) {
        $php_error_label = $errstr . ' in ' . $errfile . ' on line ' . strval($errline) . ' @ ' . get_self_url_easy();
        error_log('PHP ' . ucwords($type) . ': ' . $php_error_label, 0);
    }

    $error_str = 'PHP ' . strtoupper($type) . ' [' . strval($errno) . '] ' . $errstr . ' in ' . $errfile . ' on line ' . strval($errline);

    fatal_exit($error_str);
    return null;
}

function _sanitise_error_msg($text)
{
    if (get_option('dev_mode') == '1') {
        return $text;
    }

    // Strip paths, for security reasons
    return str_replace(array(get_file_base() . '/', get_file_base() . '/'), array('', ''), $text);
}

function _generic_exit($text, $template)
{
    if (throwing_errors()) {
        throw new CMSException($text);
    }

    if (get_param_integer('keep_fatalistic', 0) == 1) {
        fatal_exit($text);
    }

    cms_ob_end_clean(); // Emergency output, potentially, so kill off any active buffer

    $text = _sanitise_error_msg($text);

    if (!headers_sent()) {
        header('Content-type: text/html; charset=utf-8');
        header('Content-Disposition: inline');
    }

    if ($template == 'WARN_SCREEN') {
        if (http_response_code() == 200) {
            http_response_code(500);
        }

        $title = 'Warning';
    } else {
        $title = 'Message';
    }

    $middle = do_template($template, array('TEXT' => $text));
    $echo = globalise($title, $middle);
    $echo->evaluate_echo();

    exit();
}

function _fatal_exit($text, $_trace)
{
    static $already_failing = false;
    if ($already_failing) {
        exit(escape_html($text));
    }
    $already_failing = true;

    cms_ob_end_clean(); // Emergency output, potentially, so kill off any active buffer

    $text = _sanitise_error_msg($text);

    if (throwing_errors()) {
        throw new CMSException($text);
    }

    if (!headers_sent()) {
        header('Content-type: text/html; charset=utf-8');
        header('Content-Disposition: inline');
    }

    if (http_response_code() == 200) {
        http_response_code(500);
    }

    $may_see_trace = may_see_stack_dumps();
    if ($may_see_trace) {
        $trace = get_html_trace($_trace);
    } else {
        $trace = '';
    }

    if (get_param_integer('keep_fatalistic', 0) == 0) {
        $php_error_label = $text . ' @ ' . get_self_url_easy();
        error_log('Conposr: ' . $php_error_label, 0);
    }

    $middle = do_template('FATAL_SCREEN', array('TEXT' => $text, 'TRACE' => $trace));
    $echo = globalise('Error', $middle);
    $echo->evaluate_echo();

    exit();
}

function may_see_stack_dumps()
{
    return (is_admin()) || (get_option('dev_ip') == get_ip_address());
}

function get_html_trace($_trace = null)
{
    if ($_trace === null) {
        $_trace = debug_backtrace();
    }
    $trace = '<h2>Stack trace&hellip;</h2>';
    foreach ($_trace as $i => $stage) {
        if ($i > 20) {
            break;
        }

        $traces = '';
        foreach ($stage as $key => $value) {
            $_value = put_value_in_stack_trace($value);

            $traces .= ucfirst($key) . ' -> ' . $_value . '<br />' . "\n";
        }
        $trace .= '<p>' . $traces . '</p>' . "\n";
    }

    return $trace;
}

function put_value_in_stack_trace($value)
{
    try {
        if (($value === null) || (is_array($value) && (strlen(serialize($value)) > 100))) {
            $_value = gettype($value);
        } elseif (is_object($value) && (is_a($value, 'Tempcode'))) {
            if (strlen(serialize($value)) > 1000) { // NB: We can't do an eval on GLOBAL_HTML_WRAP because it may be output streaming, incomplete
                $_value = 'Tempcode -> ...';
            } else {
                $_value = $value->evaluate();
                if (!is_string($_value)) {
                    $_value = 'Tempcode -> ' . gettype($_value);
                } else {
                    $_value = 'Tempcode -> ' . $_value;
                }
            }
        } elseif ((is_array($value)) || (is_object($value))) {
            $_value = serialize($value);
        } elseif (is_string($value)) {
            $_value = '\'' . php_addslashes($value) . '\'';
        } elseif (is_float($value)) {
            $_value = float_to_raw_string($value);
        } elseif (is_integer($value)) {
            $_value = integer_format($value);
        } elseif (is_bool($value)) {
            $_value = $value ? 'true' : 'false';
        } else {
            $_value = strval($value);
        }
    } catch (Exception $e) { // Can happen for SimpleXMLElement or PDO
        $_value = '...';
    }

    return escape_html($_value);
}

function _access_denied($message)
{
    http_response_code(401); // Stop spiders ever storing the URL that caused this

    if (throwing_errors()) {
        throw new CMSException($message);
    }

    if (get_param_integer('keep_fatalistic', 0) == 1) {
        fatal_exit($message);
    }

    $login_url = get_option('login_url');

    if ($login_url !== null) {
        header('Location: ' . $login_url);
        exit();
    }

    warn_exit($message); // Or if no login screen, just show normal error screen
}

function set_throw_errors($_throwing_errors = true)
{
    global $THROWING_ERRORS;
    $THROWING_ERRORS = $_throwing_errors;
}

function throwing_errors()
{
    global $THROWING_ERRORS;
    return $THROWING_ERRORS;
}

class CMSException extends Exception
{
    public function __construct($msg)
    {
        if (is_object($msg)) {
            $msg = strip_html($msg->evaluate());
        }

        parent::__construct($msg);
    }
}
