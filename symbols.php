<?php /*

 Conposr (Composr-lite framework for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    Conposr
 */

function ecv($escaped, $type, $name, $param)
{
    // SYMBOLS...

    if ($type === TC_SYMBOL) {
        // Built-in
        if ($name === '?') {
            $value = call_user_func('ecv_TERNARY', $escaped, $param);
        } elseif (function_exists('ecv_' . $name)) {
            $value = call_user_func('ecv_' . $name, $escaped, $param);
        } elseif (defined($name)) {
            $value = strval(constant($name));
            if (!is_string($value)) {
                $value = strval($value);
            }

            if ($escaped !== array()) {
                if (is_object($value)) {
                    $value = $value->evaluate();
                }
                apply_tempcode_escaping($escaped, $value);
            }
        } else {
            fatal_exit('Missing symbol ' . $name);
        }

        return $value;
    }

    // DIRECTIVES...

    if ($type === TC_DIRECTIVE) {
        $value = '';

        // In our param we should have a map of bubbled template parameters (under 'vars') and our numbered directive parameters

        if ($param === null) {
            $param = array();
        }

        // Closure-based Tempcode parser may send in strings, so we need to adapt...
        foreach ($param as $key => $val) {
            if (is_string($val)) {
                $param[$key] = make_string_tempcode($val);
            }
        }

        if (!isset($param['vars'])) {
            $param['vars'] = array();
        }

        switch ($name) {
            case 'IF':
                ecv_IF($value, $escaped, $param);
                break;

            case 'IF_EMPTY':
                ecv_IF_EMPTY($value, $escaped, $param);
                break;

            case 'IF_NON_EMPTY':
                ecv_IF_NON_EMPTY($value, $escaped, $param);
                break;

            case 'IF_PASSED':
                ecv_IF_PASSED($value, $escaped, $param);
                break;

            case 'IF_NON_PASSED':
                ecv_IF_NON_PASSED($value, $escaped, $param);
                break;

            case 'IF_PASSED_AND_TRUE':
                ecv_IF_PASSED_AND_TRUE($value, $escaped, $param);
                break;

            case 'IF_NON_PASSED_OR_FALSE':
                ecv_IF_NON_PASSED_OR_FALSE($value, $escaped, $param);
                break;

            case 'WHILE':
                ecv_WHILE($value, $escaped, $param);
                break;

            case 'LOOP':
                ecv_LOOP($value, $escaped, $param);
                break;

            case 'SET':
                if (isset($param[1])) {
                    unset($param['vars']);
                    global $TEMPCODE_SETGET;
                    if (count($param) == 2) {
                        $TEMPCODE_SETGET[isset($param[0]->codename/*faster than is_object*/) ? $param[0]->evaluate() : $param[0]] = $param[1];
                    } else {
                        $param_copy = array();
                        foreach ($param as $i => $x) {
                            if ($i !== 0) {
                                $param_copy[] = isset($x->codename/*faster than is_object*/) ? $x->evaluate() : $x;
                            }
                        }
                        $TEMPCODE_SETGET[isset($param[0]->codename/*faster than is_object*/) ? $param[0]->evaluate() : $param[0]] = implode(',', $param_copy);
                    }
                }
                break;

            case 'IMPLODE':
                if (isset($param[1])) {
                    $key = $param[1]->evaluate();
                    $array = array_key_exists($key, $param['vars']) ? $param['vars'][$key] : array();
                    if ((isset($param[2])) && ($param[2]->evaluate() == '1')) {
                        $delim = $param[0]->evaluate();
                        foreach ($array as $key => $val) {
                            if ($value != '') {
                                $value .= $delim;
                            }
                            $value .= escape_html((is_integer($key) ? integer_format($key) : $key) . '=' . $val);
                        }
                    } else {
                        foreach ($array as &$key) {
                            if ((isset($param[3])) && ($param[3]->evaluate() == '1')) {
                                $key = escape_html($key);
                            }
                        }
                        $value = implode($param[0]->evaluate(), $array);
                    }
                }
                break;

            case 'COUNT':
                if (isset($param[0])) {
                    $key = $param[0]->evaluate();
                    $array = array_key_exists($key, $param['vars']) ? $param['vars'][$key] : array();
                    if (is_array($array)) {
                        $value = strval(count($array));
                    }
                }
                break;

            case 'OF':
                if (isset($param[1])) {
                    $key = $param[0]->evaluate();
                    $x = $param[1]->evaluate();

                    $array = array_key_exists($key, $param['vars']) ? $param['vars'][$key] : array();
                    if (is_array($array)) {
                        $x2 = is_numeric($x) ? intval($x) : $x;
                        if (is_integer($x2)) {
                            if ($x2 < 0) {
                                $x2 = count($array) - 1;
                            } elseif ($x2 >= count($array)) {
                                $x2 -= count($array);
                            }
                        }
                        $value = array_key_exists($x2, $array) ? $array[$x2] : '';
                        if (is_object($value)) {
                            $value = $value->evaluate();
                        }
                    }
                }
                break;

            case 'INCLUDE':
                if (isset($param[1])) {
                    $tpl_params = $param['vars'];
                    $var_data = $param[count($param) - 2]->evaluate();
                    $explode = explode("\n", $var_data);
                    foreach ($explode as $val) {
                        $bits = explode('=', $val, 2);
                        if (count($bits) == 2) {
                            $save_as = ltrim($bits[0]);
                            $tpl_params[$save_as] = str_replace('\n', "\n", $bits[1]);
                        }
                    }
                    $_value = do_template($param[0]->evaluate(), $tpl_params);
                    $value = $_value->evaluate();

                    if (substr($value, 0, 3) == chr(hexdec('EF')) . chr(hexdec('BB')) . chr(hexdec('BF'))) {
                        $value = substr($value, 3);
                    }
                }
                break;

            case 'IF_IN_ARRAY':
                if (isset($param[2])) {
                    $key = $param[0]->evaluate();
                    $array = array_key_exists($key, $param['vars']) ? $param['vars'][$key] : array();
                    $value = '';
                    $i = 1;
                    while (isset($param[$i + 1])) {
                        $checking_in = $param[$i]->evaluate();
                        if (in_array($checking_in, $array)) {
                            $value = $param[count($param) - 2]->evaluate();
                            break;
                        }
                        $i++;
                    }
                }
                break;

            case 'IF_NOT_IN_ARRAY':
                if (isset($param[2])) {
                    $key = $param[0]->evaluate();
                    $array = array_key_exists($key, $param['vars']) ? $param['vars'][$key] : array();
                    $value = '';
                    $ok = true;
                    $i = 1;
                    while (isset($param[$i + 1])) {
                        $checking_in = $param[$i]->evaluate();
                        if (in_array($checking_in, $array)) {
                            $ok = false;
                            break;
                        }
                        $i++;
                    }
                    if ($ok) {
                        $value = $param[$i]->evaluate();
                    }
                }
                break;

            case 'IF_ARRAY_EMPTY':
                if (isset($param[0])) {
                    $looking_at = $param[0]->evaluate();
                    if (array_key_exists($looking_at, $param['vars'])) {
                        if ((is_array($param['vars'][$looking_at])) && (count($param['vars'][$looking_at]) == 0)) {
                            $value = $param[1]->evaluate();
                        }
                    }
                }
                break;

            case 'IF_ARRAY_NON_EMPTY':
                if (isset($param[0])) {
                    $looking_at = $param[0]->evaluate();
                    if (array_key_exists($looking_at, $param['vars'])) {
                        if ((is_array($param['vars'][$looking_at])) && (count($param['vars'][$looking_at]) != 0)) {
                            $value = $param[1]->evaluate();
                        }
                    }
                }
                break;

            case 'COMMENT':
                break;

            case 'CASES':
                if (isset($param[1])) {
                    $value = '';
                    $compare = $param[0]->evaluate();
                    $substring = (isset($param[2]) && $param[1]->evaluate() == '1');
                    $regexp = (isset($param[2]) && $param[1]->evaluate() == '2');
                    $explode = explode("\n", trim($param[isset($param[2]) ? 2 : 1]->evaluate()));
                    foreach ($explode as $i => $case) {
                        if (strpos($case, '=') === false) {
                            continue;
                        }
                        list($compare_case, $value_case) = explode('=', $case, 2);
                        $compare_case = trim($compare_case);
                        if (
                            ((!$substring) && (!$regexp) && ($compare_case == $compare)) || // Exact match
                            (($substring) && (($compare_case == '') || (strpos($compare, $compare_case) !== false))) || // Substring
                            (($regexp) && (preg_match('#' . str_replace('#', '\#', $compare_case) . '#', $compare) != 0)) || // Regexp
                            (($compare_case == '') && (!isset($explode[$i + 1]))) // The final default case
                        ) {
                            $value = $value_case;
                            break;
                        }
                    }
                }
                break;

            default:
                fatal_exit('Unknown directive ' . $name);
        }

        if ($escaped !== array()) {
            apply_tempcode_escaping($escaped, $value);
        }

        return $value;
    }

    fatal_exit('Unknown variable type');
}

function ecv_SET($escaped, $param)
{
    $value = '';

    global $TEMPCODE_SETGET;

    if (isset($param[1])) {
        if ((count($param) == 2) || (isset($param[1]) && isset($param[1]->codename)/*faster than is_object*/)) {
            $TEMPCODE_SETGET[$param[0]] = $param[1];
        } else {
            $param_copy = $param;
            unset($param_copy[0]);
            $TEMPCODE_SETGET[$param[0]] = isset($param_copy[2]/*optimisation*/) ? implode(',', $param_copy) : $param_copy[1];
        }
    } else {
        $TEMPCODE_SETGET[$param[0]] = '';
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_GET($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        global $TEMPCODE_SETGET;
        if (isset($TEMPCODE_SETGET[$param[0]])) {
            if (isset($TEMPCODE_SETGET[$param[0]]->codename)/*faster than is_object*/) {
                if ((array_key_exists(1, $param)) && (((is_string($param[1])) && ($param[1] == '1')) || ((is_object($param[1])) && ($param[1]->evaluate() == '1')))) { // no-cache
                    $TEMPCODE_SETGET[$param[0]]->decache();
                    $value = $TEMPCODE_SETGET[$param[0]]->evaluate();
                    $TEMPCODE_SETGET[$param[0]]->decache();

                    if ($escaped !== array()) {
                        apply_tempcode_escaping($escaped, $value);
                    }
                    return $value;
                }

                $TEMPCODE_SETGET[$param[0]] = $TEMPCODE_SETGET[$param[0]]->evaluate();
            }

            $value = $TEMPCODE_SETGET[$param[0]];
        }
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_TERNARY($escaped, $param)
{
    $value = '';

    if (isset($param[1])) {
        $value = ($param[0] == '1') ? $param[1] : (isset($param[2]) ? $param[2] : $value);
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_($escaped, $param) // A Tempcode comment
{
    $value = '';
    return $value;
}

function ecv_BROWSER($escaped, $param)
{
    $value = '';

    if (isset($param[1])) {
        $q = false;
        foreach (explode('|', $param[0]) as $browser) {
            $q = browser_matches($browser);
            if ($q) {
                break;
            }
        }
        $value = $q ? $param[1] : (isset($param[2]) ? $param[2] : '');
    }


    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv__GET($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        $value = get_param_string($param[0], isset($param[1]) ? $param[1] : '', true);
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_STRIP_TAGS($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        if ((isset($param[1])) && ($param[1] == '1')) {
            $value = strip_tags(str_replace('))', ')', str_replace('((', '(', str_replace('<em>', '(', str_replace('</em>', ')', $param[0])))));
        } else {
            if (strpos($param[0], '<') === false) { // optimisation
                $value = $param[0];
            } else {
                $value = strip_tags($param[0], isset($param[2]) ? $param[2] : '');
            }
        }
        if ((isset($param[1])) && ($param[1] == '1')) {
            $value = html_entity_decode($value, ENT_QUOTES, 'utf-8');
        }
        if ((!isset($param[2])) || ($param[2] == '0')) {
            $value = trim($value);
        }
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }

    return $value;
}

function ecv_TRUNCATE_LEFT($escaped, $param)
{
    $value = symbol_truncator($param, 'left');

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_TRUNCATE_SPREAD($escaped, $param)
{
    $value = symbol_truncator($param, 'spread');

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

/**
 * Handle truncation symbols in all their complexity
 *
 * @param  array $param Parameters passed to the symbol (0=text, 1=amount, 2=tooltip?, 3=is_html?, 4=use as grammatical length rather than HTML byte length, 5=fractional-deviation-tolerance for grammar-preservation)
 * @param  string $type The type of truncation to do
 * @set    left right spread
 * @param  ?mixed $tooltip_if_truncated Tooltip to add on, but only if we end up creating our own tooltip (null: none)
 * @return string The result.
 */
function symbol_truncator($param, $type, $tooltip_if_truncated = null)
{
    $value = '';

    if (is_object($param[0])) {
        $param[0] = $param[0]->evaluate();
        if (!isset($param[2])) {
            $param[2] = '0';
        }
        $param[3] = '1';
    }

    $amount = intval(isset($param[1]) ? $param[1] : '60');

    if (strlen($param[0]) < $amount) {
        return escape_html($param[0]);
    }

    $not_html = $param[0];
    $html = escape_html($param[0]);

    if ((isset($not_html[$amount])/*optimisation*/) && ((cms_mb_strlen($not_html) > $amount)) || (stripos($html, '<img') !== false)) {
        $truncated = $not_html;
        switch ($type) {
            case 'left':
                $temp = escape_html(cms_mb_substr($not_html, 0, max($amount - 3, 1)));
                if ($temp != $html && in_array(substr($temp, -1), array('.', '?', '!'))) {
                    $temp .= '<br class="ellipsis_break" />'; // so the "..." does not go right after the sentence terminator
                }
                $value = ($temp == $html) ? $temp : str_replace(array('</p>&hellip;', '</div>&hellip;'), array('&hellip;</p>', '&hellip;</div>'), (cms_trim($temp, true) . '&hellip;'));
                break;
            case 'right':
                $value = str_replace(array('</p>&hellip;', '</div>&hellip;'), array('&hellip;</p>', '&hellip;</div>'), ('&hellip;' . ltrim(escape_html(cms_mb_substr($not_html, -max($amount - 3, 1))))));
                break;
            case 'spread':
                $pos = intval(floor(floatval($amount) / 2.0)) - 1;
                $value = str_replace(array('</p>&hellip;', '</div>&hellip;'), array('&hellip;</p>', '&hellip;</div>'), cms_trim((escape_html(cms_mb_substr($not_html, 0, $pos))) . '&hellip;' . ltrim(escape_html(cms_mb_substr($not_html, -$pos - 1))), true));
                break;
        }
    } else {
        $value = $html;
    }

    return $value;
}

function ecv_IS_EMPTY($escaped, $param)
{
    $value = '1';

    if (isset($param[0])) {
        $value = ($param[0] === '') ? '1' : '0';
    }

    return $value;
}

function ecv_IS_NON_EMPTY($escaped, $param)
{
    $value = '0';

    if (isset($param[0])) {
        $value = ($param[0] !== '') ? '1' : '0';
    }

    return $value;
}

function ecv_TRIM($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        $value = $param[0];
        if ($value !== '') {
            if (strpos($value, '<') === false && strpos($value, '&') === false) {
                $value = trim($value);
            } else {
                $value = cms_trim($param[0], !isset($param[1]) || $param[1] === '1');
            }
        }
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

/**
 * Trim some text, supporting removing HTML white-space also.
 *
 * @param  string $text Input text.
 * @param  boolean $try_hard Whether to keep doing it, while it changes (if complex mixtures are on the end).
 * @return string The result text.
 */
function cms_trim($text, $try_hard = true)
{
    if ((preg_match('#[<&]#', $text) === 0) && (memory_get_usage() > 1024 * 1024 * 40)) {
        return trim($text); // Don't have enough memory
    }

    // Intentionally not using regexps, as actually using substr is a lot faster and uses much less memory

    do {
        $before = $text;
        $c = substr($text, 0, 1);
        if ($c === '<') {
            if (strtolower(substr($text, 1, 1)) === 'b') {
                if (strtolower(substr($text, 0, 6)) === '<br />') {
                    $text = substr($text, 6);
                }
                if (strtolower(substr($text, 0, 5)) === '<br/>') {
                    $text = substr($text, 5);
                }
                if (strtolower(substr($text, 0, 4)) === '<br>') {
                    $text = substr($text, 4);
                }
            }
        }
        elseif ($c == '&') {
            if (strtolower(substr($text, 0, 6)) === '&nbsp;') {
                $text = substr($text, 6);
            }
        }
        $text = ltrim($text);
    } while (($try_hard) && ($before !== $text));
    do {
        $before = $text;
        $c = substr($text, -1, 1);
        if ($c === '>') {
            if (strtolower(substr($text, -6)) === '<br />') {
                $text = substr($text, 0, -6);
            }
            if (strtolower(substr($text, -5)) === '<br/>') {
                $text = substr($text, 0, -5);
            }
            if (strtolower(substr($text, -4)) === '<br>') {
                $text = substr($text, 0, -4);
            }
        }
        elseif ($c == ';') {
            if (strtolower(substr($text, -6)) === '&nbsp;') {
                $text = substr($text, 0, -6);
            }
        }
        $text = rtrim($text);
    } while (($try_hard) && ($before !== $text));

    return $text;
}

function ecv_IS_GUEST($escaped, $param)
{
    if (isset($param[0])) {
        $value = (is_guest(intval($param[0]))) ? '1' : '0';
    } else {
        $value = is_guest() ? '1' : '0';
    }

    return $value;
}

function ecv_MEMBER($escaped, $param)
{
    $value = strval(get_member());

    return $value;
}

function ecv_USERNAME($escaped, $param)
{
    $value = get_username(get_member());

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_CYCLE($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        global $CYCLES;
        if (!isset($CYCLES[$param[0]])) {
            $CYCLES[$param[0]] = 0;
        }
        if (!isset($param[1])) { // If we can't find a param simply return the index. Poor-mans cycle reader.
            $value = strval($CYCLES[$param[0]]);
        } else { // Cycle
            if (count($param) == 2) {
                $param = array_merge(array($param[0]), explode(',', $param[1]));
            }

            ++$CYCLES[$param[0]];
            if (!array_key_exists($CYCLES[$param[0]], $param)) {
                $CYCLES[$param[0]] = 1;
            }
            $value = $param[$CYCLES[$param[0]]];
        }
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_IS_ADMIN($escaped, $param)
{
    $value = is_admin(get_member()) ? '1' : '0';

    return $value;
}

function ecv_URL_FOR_GET_FORM($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        $url_bits = parse_url($param[0]);
        if (array_key_exists('scheme', $url_bits)) {
            $value = $url_bits['scheme'] . '://' . (array_key_exists('host', $url_bits) ? $url_bits['host'] : 'localhost');
            if ((array_key_exists('port', $url_bits)) && ($url_bits['port'] != 80)) {
                $value .= ':' . strval($url_bits['port']);
            }
        }
        if (array_key_exists('path', $url_bits)) {
            $value .= $url_bits['path'];
        }
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_HIDDENS_FOR_GET_FORM($escaped, $param)
{
    $_value = new Tempcode();
    $url_bits = parse_url($param[0]);
    if ((array_key_exists('query', $url_bits)) && ($url_bits['query'] != '')) {
        foreach (explode('&', $url_bits['query']) as $exp) {
            $parts = explode('=', $exp, 2);
            if (count($parts) == 2) {
                if (!in_array($parts[0], $param)) {
                    $_value->attach(form_input_hidden($parts[0], urldecode($parts[1])));
                }
            }
        }
    }
    $value = $_value->evaluate();

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_RAND($escaped, $param)
{
    if (isset($param[1])) {
        $min = intval($param[0]);
        $max = intval($param[1]);
    } elseif (isset($param[0])) {
        $min = 0;
        $max = intval($param[0]);
    } else {
        $min = 0;
        $max = mt_getrandmax();
    }
    if ($min > $max) {
        $tmp = $min;
        $min = $max;
        $max = $tmp;
    }

    static $before = array(); // Don't allow repeats
    $key = strval($min) . '_' . strval($max);
    if (!isset($before[$key])) {
        $before[$key] = array();
    }
    do {
        $random = mt_rand($min, $max);
    }
    while (isset($before[$key][$random]));
    if (count($before[$key]) < $max - $min) {
        $before[$key][$random] = true;
    } else { // Reset, so we get another set to randomise through
        $before[$key] = array();
    }

    $value = strval($random);

    return $value;
}

function ecv_SET_RAND($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        $value = $param[mt_rand(0, count($param) - 1)];
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_DATE_AND_TIME($escaped, $param)
{
    $time = ((isset($param[0])) && ($param[0] != '')) ? intval($param[0]) : time();
    $use_contextual_dates = (isset($param[1]) && ($param[1] == '1'));
    $value = get_timezoned_date($time, true, $use_contextual_dates);

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_DATE($escaped, $param)
{
    $time = ((isset($param[0])) && ($param[0] != '')) ? intval($param[0]) : time();
    $use_contextual_dates = (isset($param[1]) && ($param[1] == '1'));
    $value = get_timezoned_date($time, false, $use_contextual_dates);

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_FROM_TIMESTAMP($escaped, $param)
{
    if (isset($param[0])) {
        $timestamp = (!empty($param[1])) ? intval($param[1]) : time();

        $value = strftime($param[0], $timestamp);
        if ($value === $param[0]) {// If no conversion happened then the syntax must have been for 'date' not 'strftime'
            $value = date($param[0], $timestamp);
        }
    } else {
        $timestamp = time();
        $value = strval($timestamp);
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_BROWSER_MATCHES($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        $q = false;
        foreach (explode('|', $param[0]) as $browser) {
            $q = browser_matches($browser);
            if ($q) {
                break;
            }
        }
        $value = $q ? '1' : '0';
    }

    return $value;
}

function ecv_INIT($escaped, $param)
{
    $value = '';

    if (isset($param[1])) {
        global $TEMPCODE_SETGET;
        if (!isset($TEMPCODE_SETGET[$param[0]])) {
            $TEMPCODE_SETGET[$param[0]] = $param[1];
        }
    }

    return $value;
}

function ecv_INC($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        global $TEMPCODE_SETGET;
        if (!isset($TEMPCODE_SETGET[$param[0]])) {
            $TEMPCODE_SETGET[$param[0]] = '0';
        }
        $TEMPCODE_SETGET[$param[0]] = strval(intval($TEMPCODE_SETGET[$param[0]]) + 1);
    }

    return $value;
}

function ecv_PREG_REPLACE($escaped, $param)
{
    $value = '';

    if (isset($param[2])) {
        $value = preg_replace('#' . str_replace('#', '\#', $param[0]) . '#' . (isset($param[3]) ? $param[3] : ''), $param[1], $param[2]);
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_MAX($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        $value = strval(max(intval($param[0]), intval($param[1])));
    }

    return $value;
}

function ecv_MIN($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        $value = strval(min(intval($param[0]), intval($param[1])));
    }

    return $value;
}

function ecv_DIV_FLOAT($escaped, $param)
{
    $value = '';

    if (isset($param[1])) {
        if (floatval($param[1]) == 0.0) {
            $value = 'divide-by-zero';
        } else {
            $value = float_to_raw_string(floatval($param[0]) / floatval($param[1]), 20, true);
        }
    }

    return $value;
}

function ecv_DIV($escaped, $param)
{
    if (isset($param[1])) {
        if (floatval($param[1]) == 0.0) {
            $value = 'divide-by-zero';
        } else {
            $value = strval(intval(floor(floatval($param[0]) / floatval($param[1]))));
        }
    }

    return $value;
}

function ecv_LCASE($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        $value = cms_mb_strtolower($param[0]);
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }

    return $value;
}

function ecv__POST($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        $value = post_param_string($param[0], isset($param[1]) ? $param[1] : '');
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_REPLACE($escaped, $param)
{
    $value = '';

    if (isset($param[2])) {
        $value = str_replace($param[0], $param[1], $param[2]);
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_IN_STR($escaped, $param)
{
    $value = '';

    if (isset($param[1])) {
        if ($param[1] == '') { // Would generate a PHP notice
            $value = '0';
        } else {
            $value = '0';
            foreach ($param as $i => $check) {
                if ((is_integer($i)) && ($i != 0) && ($check != '')) {
                    if (strpos($param[0], $check) !== false) {
                        $value = '1';
                        break;
                    }
                }
            }
        }
    }

    return $value;
}

function ecv_SUBSTR_COUNT($escaped, $param)
{
    $value = '';

    if (isset($param[1])) {
        if ($param[0] == '') {
            $value = '0';
        } else {
            $value = strval(substr_count($param[0], $param[1]));
        }
    }

    return $value;
}

function ecv_SUBSTR($escaped, $param)
{
    $value = '';

    if (isset($param[1])) {
        $value = cms_mb_substr($param[0], intval($param[1]), isset($param[2]) ? intval($param[2]) : strlen($param[0]));
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_EQ($escaped, $param)
{
    $value = '';

    if (!isset($param[0])) {
        $param[0] = '';
    }
    if (!isset($param[1])) {
        $param[1] = '';
    }
    $first = array_shift($param);
    $count = 0;
    foreach ($param as $test) {
        if ($first == $test) {
            $count++;
            break;
        }
    }
    $value = ($count !== 0) ? '1' : '0';

    return $value;
}

function ecv_NEQ($escaped, $param)
{
    $value = '';

    if (!isset($param[0])) {
        $param[0] = '';
    }
    if (!isset($param[1])) {
        $param[1] = '';
    }
    $first = array_shift($param);
    $count = 0;
    foreach ($param as $test) {
        if ($first == $test) {
            $count++;
        }
    }
    $value = ($count === 0) ? '1' : '0';

    return $value;
}

function ecv_NOT($escaped, $param)
{
    $value = '1';

    if (isset($param[0])) {
        $value = ($param[0] == '1') ? '0' : '1';
    }

    return $value;
}

function ecv_OR($escaped, $param)
{
    $value = '';

    $count = 0;
    foreach ($param as $test) {
        if ($test == '1') {
            $count++;
        }
    }
    $value = ($count > 0) ? '1' : '0';

    return $value;
}

function ecv_AND($escaped, $param)
{
    $count = 0;
    $total = 0;
    foreach ($param as $test) {
        if ($test === '1') {
            $count++;
        }
        $total++;
    }
    $value = ($count === $total) ? '1' : '0';

    return $value;
}

function ecv_NOR($escaped, $param)
{
    $count = 0;
    foreach ($param as $test) {
        if ($test === '1') {
            $count++;
        }
    }
    $value = ($count > 0) ? '0' : '1';

    return $value;
}

function ecv_NAND($escaped, $param)
{
    $count = 0;
    foreach ($param as $test) {
        if ($test === '1') {
            $count++;
        }
    }
    $value = ($count === count($param)) ? '0' : '1';

    return $value;
}

function ecv_LT($escaped, $param)
{
    $value = '';

    if (isset($param[1])) {
        $value = (intval($param[0]) < intval($param[1])) ? '1' : '0';
    }

    return $value;
}

function ecv_GT($escaped, $param)
{
    $value = '';

    if (isset($param[1])) {
        $value = (intval($param[0]) > intval($param[1])) ? '1' : '0';
    }

    return $value;
}

function ecv_SELF_URL($escaped, $param)
{
    $value = get_self_url_easy();

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_MOBILE($escaped, $param)
{
    $value = is_mobile(null, isset($param[0]) ? ($param[0] == '1') : false) ? '1' : '0';

    return $value;
}

function ecv_BASE_URL($escaped, $param)
{
    $value = get_base_url();

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_CONFIG_OPTION($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        $value = get_option($param[0]);
        if ($value === null) {
            $value = '';
        }
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_STRIP_HTML($escaped, $param)
{
    $value = strip_html($param[0]);

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_LENGTH($escaped, $param)
{
    $value = '0';

    if (isset($param[0])) {
        $value = strval(cms_mb_strlen($param[0]));
    }

    return $value;
}

function ecv_FLOAT_FORMAT($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        $value = float_format(floatval($param[0]), isset($param[1]) ? intval($param[1]) : 2, array_key_exists(2, $param) && $param[2] == '1');
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_NUMBER_FORMAT($escaped, $param)
{
    $value = '';

    if (isset($param[0])) {
        $value = integer_format(intval($param[0]));
    }

    if ($escaped !== array()) {
        apply_tempcode_escaping($escaped, $value);
    }
    return $value;
}

function ecv_IF_EMPTY(&$value, $escaped, $param)
{
    if (isset($param[1])) {
        if ($param[0]->is_empty()) {
            $value = $param[1]->evaluate();
        }
    }
}

function ecv_IF_NON_EMPTY(&$value, $escaped, $param)
{
    if (isset($param[1])) {
        if (!$param[0]->is_empty()) {
            $value = $param[1]->evaluate();
        }
    }
}

function ecv_IF_PASSED(&$value, $escaped, $param)
{
    if (isset($param[1])) {
        $t = $param[0]->evaluate();
        if (isset($param['vars'][$t])) {
            $value = $param[1]->evaluate();
        }
    }
}

function ecv_IF_NON_PASSED(&$value, $escaped, $param)
{
    if (isset($param[1])) {
        $t = $param[0]->evaluate();
        if (!isset($param['vars'][$t])) {
            $value = $param[1]->evaluate();
        }
    }
}

function ecv_IF_PASSED_AND_TRUE(&$value, $escaped, $param)
{
    if (isset($param[1])) {
        $t = $param[0]->evaluate();
        if ((isset($param['vars'][$t])) && ($param['vars'][$t] !== false) && ($param['vars'][$t] === '1')) {
            $value = $param[1]->evaluate();
        }
    }
}

function ecv_IF_NON_PASSED_OR_FALSE(&$value, $escaped, $param)
{
    if (isset($param[1])) {
        $t = $param[0]->evaluate();
        if ((!isset($param['vars'][$t])) || ($param['vars'][$t] === false) || ($param['vars'][$t] !== '1')) {
            $value = $param[1]->evaluate();
        }
    }
}

function ecv_WHILE(&$value, $escaped, $param)
{
    if (isset($param[1])) {
        $_p = $param[0]->evaluate();
        if ($_p == '1') {
            $value = '';
            $value .= $param[1]->evaluate();
            $put = '';
            ecv_WHILE($put, $escaped, $param);
            $value .= $put;
        }
    }
}

function ecv_LOOP(&$value, $escaped, $param)
{
    if (isset($param[0])) {
        $array_key = $param[0]->evaluate();

        if (!array_key_exists($array_key, $param['vars'])) {
            fatal_exit('Missing template parameter ' . $array_key);
        }

        $array = $param['vars'][$array_key];
        if (!is_array($array)) {
            $value = 'Not array';
            return;
        }

        $value = '';
        $last = count($param) - 2;
        foreach ($array as $go_key => $go) {
            if (!is_array($go)) {
                $go = array('_loop_var' => make_string_tempcode($go)); // In case it's not a list of maps, but just a list
            }

            $ps = $go + $param['vars'] + array('_loop_key' => make_string_tempcode(is_integer($go_key) ? strval($go_key) : $go_key));
            $bound = $param[$last]->bind($ps, '');
            $value .= $bound->evaluate();
        }
    }
}