<?php /*

 Conposr Framework (a Composr-lite designed for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    conposr
 */

define('PARSE_NO_MANS_LAND', 0);
define('PARSE_DIRECTIVE', 1);
define('PARSE_SYMBOL', 2);
define('PARSE_PARAMETER', 4);
define('PARSE_DIRECTIVE_INNER', 5);

global $DIRECTIVES_NEEDING_VARS;
$DIRECTIVES_NEEDING_VARS = array('IF_PASSED_AND_TRUE' => true, 'IF_NON_PASSED_OR_FALSE' => true, 'IF_NOT_IN_ARRAY' => true, 'IF_IN_ARRAY' => true, 'IMPLODE' => true, 'COUNT' => true, 'IF_ARRAY_EMPTY' => true, 'IF_ARRAY_NON_EMPTY' => true, 'OF' => true, 'INCLUDE' => true, 'LOOP' => true);

function _length_so_far($bits, $i)
{
    $len = 0;
    foreach ($bits as $_i => $x) {
        if ($_i == $i) {
            break;
        }
        $len += strlen($x);
    }
    return $len;
}

function compile_template($data, $template_name)
{
    $bits = array_values(preg_split('#(?<!\\\\)(\{(?![A-Z][a-z])(?=[\dA-Z\$\+\!\_]+[\.`%\*=\;\#\-~\^\|\'&/@+]*))|((?<!\\\\)\,)|((?<!\\\\)\})#', $data, -1, PREG_SPLIT_DELIM_CAPTURE));  // One error mail showed on a server it had weird indexes, somehow. Hence the array_values call to reindex it
    $count = count($bits);
    $stack = array();
    $current_level_mode = PARSE_NO_MANS_LAND;
    $current_level_data = array();
    $current_level_params = array();
    for ($i = 0; $i < $count; $i++) {
        $next_token = $bits[$i];
        if ($next_token === '') {
            continue;
        }
        if (($i !== $count - 1) && ($next_token === '{') && (preg_match('#^[\dA-Z\$\+\!\_]#', $bits[$i + 1]) === 0)) {
            $current_level_data[] = '"{}"';
            continue;
        }

        switch ($next_token) {
            case '{':
                // Open a new level
                $stack[] = array($current_level_mode, $current_level_data, $current_level_params, null, null, null);
                ++$i;
                $next_token = isset($bits[$i]) ? $bits[$i] : null;
                if ($next_token === null) {
                    fatal_exit('Abrupted brace or directive in template ' . $template_name . ' on line ' . integer_format(1 + substr_count(substr($data, 0, _length_so_far($bits, $i)), "\n")));
                }
                $current_level_data = array();
                switch (isset($next_token[0]) ? $next_token[0] : '') {
                    case '$':
                        $current_level_mode = PARSE_SYMBOL;
                        $current_level_data[] = '"' . php_addslashes(($next_token === '$') ? '' : substr($next_token, 1)) . '"';
                        break;
                    case '+':
                        $current_level_mode = PARSE_DIRECTIVE;
                        $current_level_data[] = '"' . php_addslashes(($next_token === '+') ? '' : substr($next_token, 1)) . '"';
                        break;
                    default:
                        $current_level_mode = PARSE_PARAMETER;
                        $current_level_data[] = '"' . php_addslashes($next_token) . '"';
                        break;
                }
                $current_level_params = array();
                break;

            case '}':
                if (($stack === array()) || ($current_level_mode === PARSE_DIRECTIVE_INNER)) {
                    $literal = php_addslashes($next_token);

                    $current_level_data[] = '"' . $literal . '"';
                    break;
                }

                $opener_params = array_merge($current_level_params, array($current_level_data));
                $__first_param = array_shift($opener_params);
                if (count($__first_param) !== 1) {
                    fatal_exit('You aren\'t allowed to build up variable names dynamically via nesting, they should be alphanumeric strings with optional trailing escaping characters.');
                }
                $_first_param = $__first_param[0];

                if (($bits[$i - 1] === '') && (count($current_level_data) === 0)) {
                    $current_level_data[] = '""';
                }

                // Return to the previous level
                $past_level_data = $current_level_data;
                $past_level_params = $current_level_params;
                $past_level_mode = $current_level_mode;
                if ($stack === array()) {
                    fatal_exit('Brace/directive mismatch: too many closes, or closed one that was not open in' . $template_name . ' on line ' . integer_format(1 + _length_so_far($bits, $i)));
                } else {
                    list($current_level_mode, $current_level_data, $current_level_params, , ,) = array_pop($stack);
                }

                // Handle the level we just closed
                $_escaped = str_split(preg_replace('#[^:\.`%\*=\;\#\-~\^\|\'&/@+]:?#', '', $_first_param)); // :? is so that the ":" in language string IDs does not get considered an escape
                $escaped = array();
                foreach ($_escaped as $e) {
                    switch ($e) {
                        case '%':
                            $escaped[] = NAUGHTY_ESCAPED;
                            break;
                        case '*':
                            $escaped[] = ENTITY_ESCAPED;
                            break;
                        case ';':
                            $escaped[] = SQ_ESCAPED;
                            break;
                        case '#':
                            $escaped[] = DQ_ESCAPED;
                            break;
                        case '~': // New lines disappear
                            $escaped[] = NL_ESCAPED;
                            break;
                        case '^':
                            $escaped[] = NL2_ESCAPED; // New lines go to \n
                            break;
                        case '|':
                            $escaped[] = ID_ESCAPED;
                            break;
                        case '&':
                            $escaped[] = UL_ESCAPED;
                            break;
                        case '/':
                            $escaped[] = JSHTML_ESCAPED;
                            break;
                    }
                }
                $_opener_params = '';
                foreach ($opener_params as $oi => &$oparam) {
                    if ($oparam === array()) {
                        $oparam = array('""');
                        if (!isset($opener_params[$oi + 1])) {
                            unset($opener_params[$oi]);
                            break;
                        }
                    }

                    if ($_opener_params !== '') {
                        $_opener_params .= ',';
                    }
                    $_opener_params .= implode('.', $oparam);
                }

                $first_param = preg_replace('#[`%*=;\#\-~\^|\'!&./@+]+(")?$#', '$1', $_first_param);
                switch ($past_level_mode) {
                    case PARSE_SYMBOL:
                        $name = preg_replace('#(^")|("$)#', '', $first_param);
                        if ($name === '?') {
                            $name = 'TERNARY';
                        }
                        if (function_exists('ecv_' . $name)) {
                            $new_line = 'ecv_' . $name . '(array(' . implode(',', $escaped) . '),array(' . $_opener_params . '))';
                        } else {
                            $new_line = 'ecv(array(' . implode(',', $escaped) . '),' . strval(TC_SYMBOL) . ',' . $first_param . ',array(' . $_opener_params . '))';
                        }
                        $current_level_data[] = $new_line;
                        break;

                    case PARSE_PARAMETER:
                        $parameter = str_replace('"', '', str_replace("'", '', $first_param));
                        $parameter = preg_replace('#[^\w]#', '', $parameter); // security to stop PHP injection

                        $temp = 'otp(isset($bound_' . php_addslashes($parameter) . ')?$bound_' . php_addslashes($parameter) . ':null';
                        if ((!function_exists('get_value')) || (get_value('shortened_tempcode') !== '1')) {
                            $temp .= ',"' . php_addslashes($parameter . '/' . $template_name) . '"';
                        }
                        $temp .= ')';

                        if ($escaped === array()) {
                            $current_level_data[] = $temp;
                        } else {
                            $s_escaped = '';
                            foreach ($escaped as $esc) {
                                if ($s_escaped !== '') {
                                    $s_escaped .= ',';
                                }
                                $s_escaped .= strval($esc);
                            }
                            if ($s_escaped === strval(ENTITY_ESCAPED)) {
                                $current_level_data[] = '(empty($bound_' . $parameter . '->pure_lang)?apply_tempcode_escaping_inline(array(' . $s_escaped . '),' . $temp . '):' . $temp . ')';
                            } else {
                                $current_level_data[] = 'apply_tempcode_escaping_inline(array(' . $s_escaped . '),' . $temp . ')';
                            }
                        }

                        break;
                }

                // Handle directive nesting
                if ($past_level_mode === PARSE_DIRECTIVE) {
                    $tpl_funcs = array();
                    $eval = debug_eval('return ' . $first_param . ';', $tpl_funcs, array());
                    if (!is_string($eval)) {
                        $eval = '';
                    }
                    if ($eval === 'START') { // START
                        // Open a new directive level
                        $stack[] = array($current_level_mode, $current_level_data, $current_level_params, $past_level_mode, $past_level_data, $past_level_params);
                        $current_level_data = array();
                        $current_level_params = array();
                        $current_level_mode = PARSE_DIRECTIVE_INNER;
                    } elseif ($eval === 'END') { // END
                        // Test that the top stack does represent a started directive, and close directive level
                        $past_level_data = $current_level_data;
                        if ($past_level_data === array()) {
                            $past_level_data = array('""');
                        }
                        $past_level_params = $current_level_params;
                        $past_level_mode = $current_level_mode;
                        if ($stack === array()) {
                            fatal_exit('Brace/directive mismatch: too many closes, or closed one that was not open in ' . $template_name . ' on line ' . integer_format(1 + substr_count(substr($data, 0, _length_so_far($bits, $i)), "\n")));
                        }
                        list($current_level_mode, $current_level_data, $current_level_params, $directive_level_mode, $directive_level_data, $directive_level_params) = array_pop($stack);
                        if (!is_array($directive_level_params)) {
                            fatal_exit('Non-closed brace or directive in template ' . $template_name . ' on line ' . integer_format(1 + substr_count(substr($data, 0, _length_so_far($bits, $i)), "\n")));
                        }
                        $directive_opener_params = array_merge($directive_level_params, array($directive_level_data));
                        if (($directive_level_mode !== PARSE_DIRECTIVE) || ($directive_opener_params[0][0] !== '"START"')) {
                            fatal_exit('Brace/directive mismatch: too many closes, or closed one that was not open in ' . $template_name . ' on line ' . integer_format(1 + substr_count(substr($data, 0, _length_so_far($bits, $i)), "\n")));
                        }

                        // Handle directive
                        if (count($directive_opener_params) === 1) {
                            fatal_exit('No directive type specified in ' . $template_name . ' on line ' . integer_format(1 + substr_count(substr($data, 0, _length_so_far($bits, $i)), "\n")));
                        }
                        $directive_params = '';
                        $first_directive_param = '""';
                        if ($directive_opener_params[1] === array()) {
                            $directive_opener_params[1] = array('""');
                        }
                        $count_directive_opener_params = count($directive_opener_params);
                        for ($j = 2; $j < $count_directive_opener_params; $j++) {
                            if ($directive_opener_params[$j] === array()) {
                                $directive_opener_params[$j] = array('""');
                            }

                            if ($directive_params !== '') {
                                $directive_params .= ',';
                            }
                            $directive_params .= implode('.', $directive_opener_params[$j]);

                            if ($j === 2) {
                                $first_directive_param = implode('.', $directive_opener_params[$j]);
                            }
                        }
                        $tpl_funcs = array();
                        $eval = debug_eval('return ' . implode('.', $directive_opener_params[1]) . ';', $tpl_funcs, array());
                        if (!is_string($eval)) {
                            $eval = '';
                        }
                        $directive_name = $eval;

                        if ($directive_params !== '') {
                            $directive_params .= ',';
                        }
                        $directive_params .= implode('.', $past_level_data);

                        if (isset($GLOBALS['DIRECTIVES_NEEDING_VARS'][$directive_name])) {
                            $current_level_data[] = 'ecv(array(),' . strval(TC_DIRECTIVE) . ',' . implode('.', $directive_opener_params[1]) . ',array(' . $directive_params . ',\'vars\'=>$parameters))';
                        } else {
                            $current_level_data[] = 'ecv(array(),' . strval(TC_DIRECTIVE) . ',' . implode('.', $directive_opener_params[1]) . ',array(' . $directive_params . '))';
                        }
                    } else {
                        $tpl_funcs = array();
                        $eval = debug_eval('return ' . $first_param . ';', $tpl_funcs, array());
                        if (!is_string($eval)) {
                            $eval = '';
                        }
                        $directive_name = $eval;
                        if (isset($GLOBALS['DIRECTIVES_NEEDING_VARS'][$directive_name])) {
                            $current_level_data[] = 'ecv(array(' . implode(',', $escaped) . '),' . strval(TC_DIRECTIVE) . ',' . $first_param . ',array(' . $_opener_params . ',\'vars\'=>$parameters))';
                        } else {
                            $current_level_data[] = 'ecv(array(' . implode(',', $escaped) . '),' . strval(TC_DIRECTIVE) . ',' . $first_param . ',array(' . $_opener_params . '))';
                        }
                    }
                }
                break;

            case ',': // NB: Escaping via "\," was handled in our regexp split
                switch ($current_level_mode) {
                    case PARSE_NO_MANS_LAND:
                    case PARSE_DIRECTIVE_INNER:
                        $current_level_data[] = '","';
                        break;
                    default:
                        $current_level_params[] = $current_level_data;
                        $current_level_data = array();
                        break;
                }
                break;

            default:
                $literal = php_addslashes(str_replace(array('\,', '\}', '\{'), array(',', '}', '{'), $next_token));

                $current_level_data[] = '"' . $literal . '"';
                break;
        }
    }
    if ($stack !== array()) {
        fatal_exit('Non-closed brace or directive in template ' . $template_name . ' on line ' . integer_format(1 + substr_count(substr($data, 0, _length_so_far($bits, $i)), "\n")));
    }

    if ($current_level_data === array('')) {
        $current_level_data = array('""');
    }

    return $current_level_data;
}

function _do_template($codename, $file_path, $tcp_path)
{
    $template_contents = cms_file_get_contents_safe($file_path);

    $result = template_to_tempcode($template_contents, 0, $codename);

    $data_to_write = '<' . '?php' . "\n" . $result->to_assembly() . "\n";
    cms_file_put_contents_safe($tcp_path, $data_to_write);

    return $result;
}

function template_to_tempcode($text, $symbol_pos = 0, $codename = '')
{
    $parts = compile_template(substr($text, $symbol_pos), $codename);

    if (count($parts) === 0) {
        return new Tempcode();
    }

    $parts_groups = array();
    $parts_group = array();
    foreach ($parts as $part) {
        $parts_group[] = $part;
    }
    if ($parts_group !== array()) {
        $parts_groups[] = $parts_group;
    }

    $funcdefs = array();
    $seq_parts = array();
    foreach ($parts_groups as $parts_group) {
        $myfunc = 'tcpfunc_' . fast_uniqid() . '_' . strval(count($seq_parts) + 1);
        $funcdef = build_closure_function($myfunc, $parts_group);
        $funcdefs[$myfunc] = $funcdef;
        $seq_parts[] = array(array($myfunc, array(/* Is currently unbound */), TC_KNOWN, '', ''));
    }

    $ret = new Tempcode(array($funcdefs, $seq_parts)); // Parameters will be bound in later.
    $ret->codename = $codename;
    return $ret;
}

function build_closure_function($myfunc, $parts)
{
    if ($parts === array()) {
        $parts = array('""');
    }
    $code = '';
    foreach ($parts as $part) {
        if ($code != '') {
            $code .= ",\n\t";
        }
        $code .= $part;
    }

    if (strpos($code, '$bound') === false) {
        $funcdef = "\$tpl_funcs['$myfunc']=\$KEEP_TPL_FUNCS['$myfunc']=recall_named_function('" . uniqid('', true) . "','\$parameters',\"echo " . php_addslashes($code) . ";\");";
    } else {
        $funcdef = "\$tpl_funcs['$myfunc']=\$KEEP_TPL_FUNCS['$myfunc']=recall_named_function('" . uniqid('', true) . "','\$parameters',\"extract(\\\$parameters,EXTR_PREFIX_ALL,'bound'); echo " . php_addslashes($code) . ";\");";
    }

    return $funcdef;
}
