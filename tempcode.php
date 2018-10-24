<?php /*

 Conposr Framework (a Composr-lite designed for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    conposr
 */

define('ENTITY_ESCAPED', 1); // HTML entities
define('SQ_ESCAPED', 2); // Single quotes
define('DQ_ESCAPED', 3); // Double quotes
define('NL_ESCAPED', 4); // New lines disappear
define('UL_ESCAPED', 6); // URL
define('JSHTML_ESCAPED', 7); // JavaScript </ -> <\/
define('NL2_ESCAPED', 8); // New lines go to \n
define('ID_ESCAPED', 9); // Strings to to usable IDs

define('TC_SYMBOL', 0);
define('TC_KNOWN', 1); // Either Tempcode or string
define('TC_PARAMETER', 3); // A late parameter for a compiled template
define('TC_DIRECTIVE', 4);

global $FULL_RESET_VAR_CODE, $RESET_VAR_CODE, $KEEP_TPL_FUNCS;
// && substr($x, 0, 6) == \'bound_\' removed from the below for performance, not really needed
$FULL_RESET_VAR_CODE = 'foreach(get_defined_vars() as $x => $_) { if ($x[0]==\'b\' && $x[1]==\'o\') unset($$x); } extract($parameters,EXTR_PREFIX_ALL,\'bound\');';
$RESET_VAR_CODE = 'extract($parameters,EXTR_PREFIX_ALL,\'bound\');';
$KEEP_TPL_FUNCS = array();

global $TEMPCODE_SETGET, $CYCLES;
$TEMPCODE_SETGET = array();
$CYCLES = array();

global $PHP_REP_FROM, $PHP_REP_TO, $PHP_REP_TO_TWICE;
$PHP_REP_FROM = array('\\', "\n", '$', '"', "\0");
$PHP_REP_TO = array('\\\\', '\n', '\$', '\\"', '\0');
$PHP_REP_TO_TWICE = array('\\\\\\\\', '\\n', '\\\\$', '\\\\\"', '\\0');

require_code('symbols');

function static_evaluate_tempcode($ob)
{
    return $ob->evaluate();
}

function php_addslashes($in)
{
    global $PHP_REP_FROM, $PHP_REP_TO;
    return str_replace($PHP_REP_FROM, $PHP_REP_TO, $in);
}

function php_addslashes_twice($in)
{
    $in2 = php_addslashes($in);
    return ($in === $in2) ? $in : php_addslashes($in2);
}

function fast_uniqid()
{
    return uniqid('', true);
}

function otp($var, $origin = '')
{
    switch (gettype($var)) {
        case 'NULL':
            missing_template_parameter($origin);
        case 'string':
            return $var;
        case 'object':
            return $var->evaluate();
        case 'boolean':
            return $var ? '1' : '0';
        case 'array':
            $cnt = count($var);
            return ($cnt === 0) ? '' : strval($cnt);
    }
    return '';
}

function missing_template_parameter($origin)
{
    fatal_exit('Missing parameter @ ' . $origin);
}

function build_closure_tempcode($type, $name, $parameters, $escaping = null)
{
    if ($escaping === null) {
        $_escaping = 'array()';
    } else {
        $_escaping = 'array(' . @implode(',', $escaping) . ')';
    }

    $_type = strval($type);

    if ((function_exists('ctype_alnum')) && (ctype_alnum(str_replace(array('_', '-'), array('', ''), $name)))) {
        $_name = $name;
    } else {
        if (preg_match('#^[\w\-]*$#', $name) === 0) {
            $_name = php_addslashes_twice($name);
        } else {
            $_name = $name;
        }
    }

    static $generator_base = null;
    static $generator_num = 0;
    if ($generator_base === null) {
        $generator_base = uniqid('', true);
    }
    $generator_num++;

    $has_tempcode = false;
    foreach ($parameters as $parameter) {
        if (isset($parameter->codename)/*faster than is_object*/) {
            $has_tempcode = true;
        }
    }

    $myfunc = 'do_runtime_' . $generator_base . '_' . strval($generator_num)/*We'll inline it actually rather than calling, for performance   fast_uniqid()*/;
    if ($name === '?' && $type === TC_SYMBOL) {
        $name = 'TERNARY';
    }

    if ($has_tempcode) {
        $funcdef = "\$tpl_funcs['$myfunc']=\"foreach (\\\$parameters as \\\$i=>\\\$p) { if (is_object(\\\$p)) \\\$parameters[\\\$i]=\\\$p->evaluate(); } echo ";
        if (($type === TC_SYMBOL) && (function_exists('ecv_' . $name))) {
            $funcdef .= "ecv_" . $name . "(\\\$cl," . ($_escaping) . ",\\\$parameters);\";\n";
        } else {
            $funcdef .= "ecv(\\\$cl," . ($_escaping) . "," . ($_type) . ",\\\"" . ($_name) . "\\\",\\\$parameters);\";\n";
        }
    } else {
        $_parameters = '';
        if ($parameters !== null) {
            foreach ($parameters as $parameter) {
                if ($_parameters != '') {
                    $_parameters .= ',';
                }
                if (is_bool($parameter)) {
                    $_parameters .= "\\\"" . ($parameter ? '1' : '0') . "\\\"";
                } else {
                    $_parameters .= "\\\"" . php_addslashes_twice($parameter) . "\\\"";
                }
            }
        }

        $funcdef = "\$tpl_funcs['$myfunc']=\"echo ";
        if (($type === TC_SYMBOL) && (function_exists('ecv_' . $name))) {
            $funcdef .= "ecv_" . $name . "(\\\$cl," . ($_escaping) . ",array(" . $_parameters . "));\";\n";
        } else {
            $funcdef .= "ecv(\\\$cl," . ($_escaping) . "," . ($_type) . ",\\\"" . ($_name) . "\\\",array(" . $_parameters . "));\";\n";
        }

        $parameters = array();
    }

    $ret = new Tempcode(array(array($myfunc => $funcdef), array(array(array($myfunc, ($parameters === null) ? array() : $parameters, $type, $name, '')))));
    return $ret;
}

function make_string_tempcode($string)
{
    static $generator_base = null;
    static $generator_num = 0;
    if ($generator_base === null) {
        $generator_base = uniqid('', true);
    }
    $generator_num++;

    $myfunc = 'string_attach_' . $generator_base . '_' . strval($generator_num)/*We'll inline it actually rather than calling, for performance   fast_uniqid()*/;
    $code_to_preexecute = array($myfunc => "\$tpl_funcs['$myfunc']=\"echo \\\"" . php_addslashes_twice($string) . "\\\";\";\n");
    $seq_parts = array(array(array($myfunc, array(), TC_KNOWN, '', '')));
    return new Tempcode(array($code_to_preexecute, $seq_parts));
}

function apply_tempcode_escaping($escaped, &$value)
{
    foreach ($escaped as $escape) {
        if ($escape === ENTITY_ESCAPED) {
            $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'utf-8');
        } elseif ($escape === SQ_ESCAPED) {
            $value = str_replace('&#039;', '\&#039;', str_replace('\'', '\\\'', str_replace('\\', '\\\\', $value)));
        } elseif ($escape === DQ_ESCAPED) {
            $value = str_replace('&quot;', '\&quot;', str_replace('"', '\\"', str_replace('\\', '\\\\', $value)));
        } elseif ($escape === NL_ESCAPED) {
            $value = str_replace(array("\r", "\n"), array('', ''), $value);
        } elseif ($escape === NL2_ESCAPED) {
            $value = str_replace(array("\r", "\n"), array('', '\n'), $value);
        } elseif ($escape === UL_ESCAPED) {
            $value = cms_url_encode($value);
        } elseif ($escape === JSHTML_ESCAPED) {
            $value = str_replace(']]>', ']]\'+\'>', str_replace('</', '<\/', $value));
        } elseif ($escape === ID_ESCAPED) {
            $value = fix_id($value);
        }
    }

    return $value;
}

function apply_tempcode_escaping_inline($escaped, $value)
{
    foreach ($escaped as $escape) {
        if ($escape === ENTITY_ESCAPED) {
            $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'utf-8');
        } elseif ($escape === SQ_ESCAPED) {
            $value = str_replace('&#039;', '\&#039;', str_replace('\'', '\\\'', str_replace('\\', '\\\\', $value)));
        } elseif ($escape === DQ_ESCAPED) {
            $value = str_replace('&quot;', '\&quot;', str_replace('"', '\\"', str_replace('\\', '\\\\', $value)));
        } elseif ($escape === NL_ESCAPED) {
            $value = str_replace(array("\r", "\n"), array('', ''), $value);
        } elseif ($escape === NL2_ESCAPED) {
            $value = str_replace(array("\r", "\n"), array('', '\n'), $value);
        } elseif ($escape === UL_ESCAPED) {
            $value = cms_url_encode($value);
        } elseif ($escape === JSHTML_ESCAPED) {
            $value = str_replace(']]>', ']]\'+\'>', str_replace('</', '<\/', $value));
        } elseif ($escape === ID_ESCAPED) {
            $value = fix_id($value);
        }
    }

    return $value;
}

function do_template($codename, $parameters = null)
{
    $file_path = get_file_base() . '/templates/' . $codename . '.tpl';
    if (!is_file($file_path)) {
        $file_path = get_file_base() . '/conposr/templates/' . $codename . '.tpl';
    }

    $tcp_path = get_file_base() . '/conposr/caches/templates/' . $codename . '.tcp';

    if (filemtime($file_path) < filemtime($tcp_path)) {
        $_data = new Tempcode();
        $test = $_data->from_assembly_executed($tcp_path);
    } else {
        require_code('tempcode_compiler');
        $_data = _do_template($codename, $file_path, $tcp_path);
    }

    // Bind parameters
    $ret = $_data->bind($parameters, $codename);

    return $ret;
}

class Tempcode
{
    public $code_to_preexecute;
    public $seq_parts; // List of list of closure pairs: (0) function name, and (1) parameters, (2) type, (3) name         We use a 2D list to make attach ops very fast
    public $evaluate_echo_offset_group = 0;
    public $evaluate_echo_offset_inner = 0;

    public $codename = ':container'; // The name of the template it came from

    public $preprocessed = false;
    public $cached_output;

    public $children = null, $fresh = null;

    public function __construct($details = null)
    {
        $this->cached_output = null;

        if (!isset($details)) {
            $this->preprocessable_bits = array();
            $this->seq_parts = array();
            $this->code_to_preexecute = array();
        } else {
            $this->code_to_preexecute = $details[0];
            $this->seq_parts = $details[1];
        }
    }

    public function __sleep()
    {
        return array('code_to_preexecute', 'seq_parts', 'codename');
    }

    public function decache()
    {
        foreach ($this->seq_parts as &$seq_parts_group) {
            foreach ($seq_parts_group as &$seq_part) {
                foreach ($seq_part[1] as $val) {
                    if (isset($val->codename/*faster than is_object*/)) {
                        $val->decache();
                    }
                }
            }
        }
        $this->cached_output = null;
    }

    public function parse_from(&$code, &$pos, &$len)
    {
        $this->cached_output = null;
        require_code('tempcode_compiler');
        $temp = template_to_tempcode(substr($code, $pos, $len - $pos), 0, false, '');
        $this->code_to_preexecute = $temp->code_to_preexecute;
        $this->seq_parts = $temp->seq_parts;
    }

    public function attach($attach)
    {
        if ($attach === '') {
            return;
        }

        unset($this->is_empty);

        $this->cached_output = null;

        if (isset($attach->codename)/*faster than is_object*/) { // Consider it another piece of Tempcode
            foreach ($attach->seq_parts as $seq_part_group) {
                $this->seq_parts[] = $seq_part_group;
            }

            $this->code_to_preexecute += $attach->code_to_preexecute;
        } else { // Consider it a string
            if (end($this->seq_parts) !== false) {
                $end = &$this->seq_parts[key($this->seq_parts)];
            } else {
                $this->seq_parts[] = array();
                $end = &$this->seq_parts[0];
            }

            static $generator_base = null;
            static $generator_num = 0;
            if ($generator_base === null) {
                $generator_base = uniqid('', true);
            }
            $generator_num++;

            $myfunc = 'string_attach_' . $generator_base . '_' . strval($generator_num);/*We'll inline it actually rather than calling, for performance   fast_uniqid()*/
            $funcdef = "\$tpl_funcs['$myfunc']=\"echo \\\"" . php_addslashes_twice($attach) . "\\\";\";\n";
            $this->code_to_preexecute[$myfunc] = $funcdef;
            $end[] = array($myfunc, array(), TC_KNOWN, '', '');
        }

        $this->codename = '(mixed)';
    }

    public function to_assembly()
    {
        return 'return unserialize("' . php_addslashes(serialize(array($this->seq_parts, $this->codename, $this->code_to_preexecute))) . '");' . "\n";
    }

    public function from_assembly_executed($file)
    {
        $result = include($file); // We don't eval on this because we want it to potentially be op-code cached by e.g. Zend Accelerator
        if (!is_array($result)) {
            return false; // May never get here, as PHP fatal errors can't be suppressed or skipped over
        }

        $this->cached_output = null;
        list($this->seq_parts,  $this->codename, $this->code_to_preexecute) = $result;

        return true;
    }

    public function from_assembly(&$raw_data, $allow_failure = false)
    {
        $result = eval($raw_data);

        $this->cached_output = null;
        list($this->seq_parts, $this->codename, $this->code_to_preexecute) = $result;

        return true;
    }

    public function parameterless($at)
    {
        $i = 0;
        foreach ($this->seq_parts as $seq_parts_group) {
            foreach ($seq_parts_group as $seq_part) {
                if ($i === $at) {
                    return ($seq_part[1] === array());
                }
                $i++;
            }
        }
        return false;
    }

    public function bind(&$parameters, $codename)
    {
        $out = new Tempcode();
        $out->codename = $codename;
        $out->code_to_preexecute = $this->code_to_preexecute;

        // Check parameters
        foreach ($parameters as $key => $parameter) {
            $p_type = gettype($parameter);
            if ($p_type === 'boolean') {
                $parameters[$key] = $parameter ? '1' : '0';
            } elseif (($p_type !== 'array') && ($p_type !== 'NULL')) {
                fatal_exit('Should not bind numeric values, on ' . $codename);
            }
        }

        $out->seq_parts[0] = array();
        foreach ($this->seq_parts as $seq_parts_group) {
            foreach ($seq_parts_group as $seq_part) {
                if (($seq_part[0][0] !== 's') || (substr($seq_part[0], 0, 14) !== 'string_attach_')) {
                    $seq_part[1] = &$parameters; // & is to preserve memory
                }
                $out->seq_parts[0][] = $seq_part;
            }
        }

        return $out;
    }

    public function is_empty_shell()
    {
        foreach ($this->seq_parts as $seq_parts_group) {
            if (isset($seq_parts_group[0])) {
                return false;
            }
        }
        return true;
    }

    public function is_empty()
    {
        if ($this->cached_output !== null) {
            return strlen($this->cached_output) === 0;
        }
        if (isset($this->is_empty)) {
            return $this->is_empty;
        }

        ob_start();

        global $KEEP_TPL_FUNCS, $FULL_RESET_VAR_CODE, $RESET_VAR_CODE;

        $first_of_long = isset($this->seq_parts[0][3]) || isset($this->seq_parts[3]); // We set this to know not to dig right through to determine emptiness, as this wastes cache memory (it's a tradeoff)
        $tpl_funcs = $KEEP_TPL_FUNCS;

        foreach ($this->seq_parts as $seq_parts_group) {
            foreach ($seq_parts_group as $seq_part) {
                $seq_part_0 = $seq_part[0];
                if (!isset($tpl_funcs[$seq_part_0])) {
                    eval($this->code_to_preexecute[$seq_part_0]);
                }
                if (($tpl_funcs[$seq_part_0][0] !== 'e'/*for echo*/) && (function_exists($tpl_funcs[$seq_part_0]))) {
                    call_user_func($tpl_funcs[$seq_part_0], $seq_part[1], $seq_part[4]);
                } else {
                    $parameters = $seq_part[1];
                    eval($tpl_funcs[$seq_part_0]);
                }

                if (($first_of_long) || (ob_get_length() > 0)) { // We only quick exit on the first iteration, as we know we likely didn't spend much time getting to it- anything more and we finish so that we can cache for later use by evaluate/evaluate_echo
                    ob_end_clean();
                    $this->is_empty = false;
                    return false;
                }

                $first_of_long = false;
            }
        }

        $tmp = ob_get_clean();
        $this->cached_output = $tmp; // Optimisation to store it in here. We don't do the same for evaluate_echo as that's a final use case and hence it would be unnecessarily inefficient to store the result

        $ret = ($tmp === '');
        $this->is_empty = $ret;

        return $ret;
    }

    public function __toString()
    {
        return $this->evaluate();
    }

    public function evaluate($current_lang = null)
    {
        if (isset($this->cached_output)) {
            return $this->cached_output;
        }

        global $KEEP_TPL_FUNCS, $FULL_RESET_VAR_CODE, $RESET_VAR_CODE;
        $tpl_funcs = $KEEP_TPL_FUNCS;

        ob_start();

        foreach ($this->seq_parts as $seq_parts_group) {
            foreach ($seq_parts_group as $seq_part) {
                $seq_part_0 = $seq_part[0];
                if (!isset($tpl_funcs[$seq_part_0])) {
                    eval($this->code_to_preexecute[$seq_part_0]);
                }
                if (($tpl_funcs[$seq_part_0][0] !== 'e'/*for echo*/) && (function_exists($tpl_funcs[$seq_part_0]))) {
                    call_user_func($tpl_funcs[$seq_part_0], $seq_part[1], $seq_part[4]);
                } else {
                    $parameters = $seq_part[1];
                    eval($tpl_funcs[$seq_part_0]);
                }
            }
        }

        $ret = ob_get_clean();
        $this->cached_output = $ret; // Optimisation to store it in here. We don't do the same for evaluate_echo as that's a final use case and hence it would be unnecessarily inefficient to store the result

        return $ret;
    }

    public function evaluate_echo()
    {
        if ($this->cached_output !== null) {
            echo $this->cached_output;
            return;
        }

        global $KEEP_TPL_FUNCS, $FULL_RESET_VAR_CODE, $RESET_VAR_CODE;
        $tpl_funcs = $KEEP_TPL_FUNCS;

        foreach ($this->seq_parts as $seq_parts_group) {
            foreach ($seq_parts_group as $seq_part) {
                $seq_part_0 = $seq_part[0];
                if (!isset($tpl_funcs[$seq_part_0])) {
                    eval($this->code_to_preexecute[$seq_part_0]);
                }
                if (($tpl_funcs[$seq_part_0][0] !== 'e'/*for echo*/) && (function_exists($tpl_funcs[$seq_part_0]))) {
                    call_user_func($tpl_funcs[$seq_part_0], $seq_part[1], $seq_part[4]);
                } else {
                    $parameters = $seq_part[1];
                    eval($tpl_funcs[$seq_part_0]);
                }
            }
        }

        flush();
    }
}

function recall_named_function($id, $parameters, $code)
{
    $k = 'TEMPCODE_FUNCTION__' . $id;
    if (!isset($GLOBALS[$k])) {
        $GLOBALS[$k] = @create_function($parameters, $code);
    }
    return $GLOBALS[$k];
}

function debug_eval($code, &$tpl_funcs = null, $parameters = null, $cl = null)
{
    global $KEEP_TPL_FUNCS, $FULL_RESET_VAR_CODE, $RESET_VAR_CODE;

    if ($code === '') {
        return ''; // Blank eval returns false
    }
    $result = @eval($code);
    if ($result === false) {
        $result = '';
    }
    return $result;
}
