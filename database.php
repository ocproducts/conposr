<?php /*

 Conposr Framework (a Composr-lite designed for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    conposr
 */

$db_type = get_option('db_type');
$db_host = get_option('db_host');
$db_name = get_option('db_name');
$db_user = get_option('db_user');
$db_password = get_option('db_password');
$table_prefix = get_option('table_prefix');

require_code('database_drivers/' . $db_type);
$db_class = 'Database_Static_' . $db_type;
$static_ob = new $db_class();
$GLOBALS['DB_STATIC_OBJECT'] = $static_ob;
if ($db_name !== null) {
    $GLOBALS['SITE_DB'] = new DatabaseConnector($db_name, $db_host, $db_user, $db_password, $table_prefix, $static_ob);
}

function db_string_equal_to($attribute, $compare)
{
    return $GLOBALS['DB_STATIC_OBJECT']->db_string_equal_to($attribute, $compare);
}

function db_string_not_equal_to($attribute, $compare)
{
    return $GLOBALS['DB_STATIC_OBJECT']->db_string_not_equal_to($attribute, $compare);
}

function db_encode_like($pattern)
{
    return $GLOBALS['DB_STATIC_OBJECT']->db_encode_like($pattern);
}

function db_escape_string($string)
{
    return $GLOBALS['DB_STATIC_OBJECT']->db_escape_string($string);
}

function get_table_prefix()
{
    return get_option('table_prefix');
}

class DatabaseConnector
{
    public $table_prefix;
    public $connection;
    public $static_ob;

    public function __construct($db_name, $db_host, $db_user, $db_password, $table_prefix, $static_ob)
    {
        $this->connection = $static_ob->db_get_connection($db_name, $db_host, $db_user, $db_password);
        $this->table_prefix = $table_prefix;
        $this->static_ob = $static_ob;
    }

    public function get_table_prefix()
    {
        return $this->table_prefix;
    }

    public function query_insert($table, $map, $ret = false, $fail_ok = false)
    {
        $keys = '';
        $all_values = array(); // will usually only have a single entry; for bulk-inserts it will have as many as there are inserts

        $eis = $this->static_ob->db_empty_is_null();

        foreach ($map as $key => $value) {
            if ($keys !== '') {
                $keys .= ', ';
            }
            $keys .= $key;

            $_value = (!is_array($value)) ? array($value) : $value;

            $v = null;
            foreach ($_value as $i => $v) {
                if (!isset($all_values[$i])) {
                    $all_values[$i] = '';
                }
                $values = $all_values[$i];

                if ($values !== '') {
                    $values .= ', ';
                }

                if ($value === null) {
                    if (($eis) && ($v === '')) {
                        $values .= '\' \'';
                    } else {
                        $values .= 'NULL';
                    }
                } else {
                    if (($eis) && ($v === '')) {
                        $v = ' ';
                    }
                    if (is_integer($v)) {
                        $values .= strval($v);
                    } elseif (is_float($v)) {
                        $values .= float_to_raw_string($v, 10);
                    } else {
                        $values .= '\'' . $this->static_ob->db_escape_string($v) . '\'';
                    }
                }

                $all_values[$i] = $values; // essentially appends, as $values was loaded from former $all_values[$i] value
            }
        }

        if (count($all_values) === 1) { // usually $all_values only has length of 1
            $query = 'INSERT INTO ' . $this->table_prefix . $table . ' (' . $keys . ') VALUES (' . $all_values[0] . ')';
        } else {
            if (count($all_values) === 0) {
                return null;
            }

            // So we can do batch inserts...
            $all_v = '';
            foreach ($all_values as $v) {
                if ($all_v !== '') {
                    $all_v .= ', ';
                }
                $all_v .= '(' . $v . ')';
            }

            $query = 'INSERT INTO ' . $this->table_prefix . $table . ' (' . $keys . ') VALUES ' . $all_v;
        }

        return $this->_query($query, null, null, $fail_ok, $ret, null, '');
    }

    protected function _get_where_expand($table, $select_map = null, $where_map = null, $end = '')
    {
        if ($select_map === null) {
            $select_map = array('*');
        }

        $select = '';
        foreach ($select_map as $key) {
            if ($select !== '') {
                $select .= ',';
            }

            $select .= $key;
        }

        $where = '';
        if (($where_map !== null) && ($where_map != array())) {
            foreach ($where_map as $key => $value) {
                if ($where !== '') {
                    $where .= ' AND ';
                }

                if (is_float($value)) {
                    $where .= $key . '=' . float_to_raw_string($value, 10);
                } elseif (is_integer($value)) {
                    $where .= $key . '=' . strval($value);
                } else {
                    if ($value === null) {
                        $where .= $key . ' IS NULL';
                    } else {
                        if (($value === '') && ($this->static_ob->db_empty_is_null())) {
                            $value = ' ';
                        }

                        $where .= db_string_equal_to($key, $value);
                    }
                }
            }

            return 'SELECT ' . $select . ' FROM ' . $table . ' WHERE (' . $where . ') ' . $end;
        }
        if (substr(ltrim($end), 0, 6) !== 'WHERE ') {
            $end = 'WHERE 1=1 ' . $end; // We force a WHERE so that code of ours that alters queries can work robustly
        }
        return 'SELECT ' . $select . ' FROM ' . $table . ' ' . $end;
    }

    public function query_select_value($table, $selected_value, $where_map = null, $end = '', $fail_ok = false)
    {
        $values = $this->query_select($table, array($selected_value), $where_map, $end, 1, null, $fail_ok);
        if ($values === null) {
            return null; // error
        }
        if (!array_key_exists(0, $values)) {
            fatal_exit('Query failed to produce a result: ' . $this->_get_where_expand($this->table_prefix . $table, array($selected_value), $where_map, $end));
        }
        return $this->_query_select_value($values);
    }

    protected function _query_select_value($values)
    {
        if (!array_key_exists(0, $values)) {
            return null; // No result found
        }
        $first = $values[0];
        $v = current($first); // Result found. Maybe a value of 'null'
        return $v;
    }

    public function query_select_value_if_there($table, $select, $where_map = null, $end = '', $fail_ok = false)
    {
        $values = $this->query_select($table, array($select), $where_map, $end, 1, null, $fail_ok);
        if ($values === null) {
            return null; // error
        }
        return $this->_query_select_value($values);
    }

    public function query_value_if_there($query, $fail_ok = false)
    {
        $values = $this->query($query, 1, null, $fail_ok);
        if ($values === null) {
            return null; // error
        }
        return $this->_query_select_value($values);
    }

    public function query_select($table, $select = null, $where_map = null, $end = '', $max = null, $start = null, $fail_ok = false)
    {
        $full_table = $this->table_prefix . $table;

        if ($select === null) {
            $select = array('*');
        }

        return $this->_query($this->_get_where_expand($full_table, $select, $where_map, $end), $max, $start, $fail_ok);
    }

    public function query_parameterised($query, $parameters, $max = null, $start = null, $fail_ok = false)
    {
        if (isset($parameters['prefix'])) {
            fatal_exit('prefix is a reserved parameter, you should not set it.');
        }

        $parameters += array('prefix' => $this->get_table_prefix());
        foreach ($parameters as $key => $val) {
            if (!is_string($val)) {
                $val = strval($val);
            }

            if ($key === 'prefix') {
                // Special case, not within quotes.
                $search = '#{' . preg_quote($key, '#') . '}#';
                $replace = $val;
            } else {
                // NB: It will always add quotes around in the query (if not already there), as that is needed for escaping to be valid.
                $search = '#\'?\{' . preg_quote($key, '#') . '\}\'?#';
                $replace = '\'' . db_escape_string($val) . '\'';
            }
            $query = preg_replace($search, $replace, $query);
        }

        return $this->query($query, $max, $start, $fail_ok);
    }

    public function query($query, $max = null, $start = null, $fail_ok = false)
    {
        return $this->_query($query, $max, $start, $fail_ok);
    }

    public function _query($query, $max = null, $start = null, $fail_ok = false, $get_insert_id = false)
    {
        return $this->static_ob->db_query($query, $this->connection, $max, $start, $fail_ok, $get_insert_id);
    }

    public function query_update($table, $update_map, $where_map = null, $end = '', $max = null, $start = null, $num_touched = false, $fail_ok = false)
    {
        $where = '';
        $update = '';

        $value = null;

        if ($where_map !== null) {
            foreach ($where_map as $key => $value) {
                if ($where !== '') {
                    $where .= ' AND ';
                }

                if (is_float($value)) {
                    $where .= $key . '=' . float_to_raw_string($value, 10);
                } elseif (is_integer($value)) {
                    $where .= $key . '=' . strval($value);
                } else {
                    if ($value === null) {
                        $where .= $key . ' IS NULL';
                    } else {
                        if (($value === '') && ($this->static_ob->db_empty_is_null())) {
                            $value = ' ';
                        }
                        $where .= db_string_equal_to($key, $value);
                    }
                }
            }
        }

        foreach ($update_map as $key => $value) {
            if ($update !== '') {
                $update .= ', ';
            }

            if ($value === null) {
                $update .= $key . '=NULL';
            } else {
                if (is_float($value)) {
                    $update .= $key . '=' . float_to_raw_string($value, 10);
                } elseif (is_integer($value)) {
                    $update .= $key . '=' . strval($value);
                } else {
                    $update .= $key . '=\'' . $this->static_ob->db_escape_string($value) . '\'';
                }
            }
        }
        if ($update === '') {
            return null;
        }

        if ($where === '') {
            return $this->_query('UPDATE ' . $this->table_prefix . $table . ' SET ' . $update . ' ' . $end, $max, $start, $fail_ok, $num_touched);
        } else {
            return $this->_query('UPDATE ' . $this->table_prefix . $table . ' SET ' . $update . ' WHERE (' . $where . ') ' . $end, $max, $start, $fail_ok, $num_touched);
        }
    }

    public function query_delete($table, $where_map = null, $end = '', $max = null, $start = null, $fail_ok = false)
    {
        if ($where_map === null) {
            if (($end === '') && ($max === null) && ($start === null)) {
                $this->_query('TRUNCATE ' . $this->table_prefix . $table, null, null, $fail_ok);
            } else {
                $this->_query('DELETE FROM ' . $this->table_prefix . $table . ' ' . $end, $max, $start, $fail_ok);
            }
            return;
        }

        $where = '';

        foreach ($where_map as $key => $value) {
            if ($where !== '') {
                $where .= ' AND ';
            }

            if (is_float($value)) {
                $where .= $key . '=' . float_to_raw_string($value, 10);
            } elseif (is_integer($value)) {
                $where .= $key . '=' . strval($value);
            } else {
                if ($value === null) {
                    $where .= $key . ' IS NULL';
                } else {
                    if (($value === '') && ($this->static_ob->db_empty_is_null())) {
                        $where .= $key . ' IS NULL'; // $value = ' ';
                    } else {
                        $where .= db_string_equal_to($key, $value);
                    }
                }
            }
        }

        $query = 'DELETE FROM ' . $this->table_prefix . $table . ' WHERE (' . $where . ') ' . $end;
        return $this->_query($query, $max, $start, $fail_ok, true);
    }
}
