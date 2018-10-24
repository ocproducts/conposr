<?php /*

 Conposr (Composr-lite framework for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    Conposr
 */

require_code('database_drivers/shared/mysql');

class Database_Static_mysqli extends Database_super_mysql
{
    public $last_select_db = null;
    public $reconnected_once = false;

    public function db_get_connection($db_name, $db_host, $db_user, $db_password, $fail_ok = false)
    {
        $db_port = 3306;
        if (strpos($db_host, ':') !== false) {
            list($db_host, $_db_port) = explode(':', $db_host);
            $db_port = intval($_db_port);
        }
        $db = @mysqli_connect($db_host, $db_user, $db_password, '', $db_port);

        if ($db === false) {
            fatal_exit('Could not connect to database-server (when authenticating) (' . mysqli_connect_error() . ')');
        }
        if (!mysqli_select_db($db, $db_name)) {
            fatal_exit('Could not connect to database (' . mysqli_error($db) . ')');
        }
        $this->last_select_db = array($db, $db_name);

        @mysqli_set_charset($db, 'utf8mb4');

        @mysqli_query($db, 'SET wait_timeout=28800');
        @mysqli_query($db, 'SET sql_big_selects=1');
        @mysqli_query($db, 'SET sql_mode=\'STRICT_ALL_TABLES\'');

        return array($db, $db_name);
    }

    public function db_escape_string($string)
    {
        if (function_exists('ctype_alnum')) {
            if (ctype_alnum($string)) {
                return $string; // No non-trivial characters
            }
        }
        if (preg_match('#[^a-zA-Z0-9\.]#', $string) === 0) {
            return $string; // No non-trivial characters
        }

        if ($this->last_select_db === null) {
            return addslashes($string);
        }
        return mysqli_real_escape_string($this->last_select_db[0], $string);
    }

    public function db_query($query, $db_parts, $max = null, $start = null, $fail_ok = false, $get_insert_id = false)
    {
        list($db, $db_name) = $db_parts;

        if ($this->last_select_db[1] !== $db_name) {
            mysqli_select_db($db, $db_name);
            $this->last_select_db = array($db, $db_name);
        }

        $this->apply_sql_limit_clause($query, $max, $start);

        $results = @mysqli_query($db, $query);
        if (($results === false) && ((!$fail_ok) || (strpos(mysqli_error($db), 'is marked as crashed and should be repaired') !== false))) {
            $err = mysqli_error($db);

            if ((function_exists('mysqli_ping')) && ($err == 'MySQL server has gone away') && (!$this->reconnected_once)) {
                ini_set('mysqli.reconnect', '1');
                $this->reconnected_once = true;
                mysqli_ping($db);
                $ret = $this->db_query($query, $db_parts, null/*already encoded*/, null/*already encoded*/, $fail_ok, $get_insert_id);
                $this->reconnected_once = false;
                return $ret;
            }

            $matches = array();
            if (preg_match('#/(\w+)\' is marked as crashed and should be repaired#U', $err, $matches) !== 0) {
                $this->db_query('REPAIR TABLE ' . $matches[1], $db_parts);
            }

            fatal_exit('Query failed: ' . $query . ' : ' . $err);
        }

        $sub = substr(ltrim($query), 0, 4);
        if (($results !== true) && (($sub === '(SEL') || ($sub === 'SELE') || ($sub === 'sele') || ($sub === 'CHEC') || ($sub === 'EXPL') || ($sub === 'REPA') || ($sub === 'DESC') || ($sub === 'SHOW')) && ($results !== false)) {
            return $this->db_get_query_rows($results, $query, $start);
        }

        if ($get_insert_id) {
            if (strtoupper(substr($query, 0, 7)) === 'UPDATE ') {
                return mysqli_affected_rows($db);
            }
            $ins = mysqli_insert_id($db);
            if ($ins === 0) {
                $table = substr($query, 12, strpos($query, ' ', 12) - 12);
                $rows = $this->db_query('SELECT MAX(id) AS x FROM ' . $table, $db_parts, 1, 0, false, false);
                return $rows[0]['x'];
            }
            return $ins;
        }

        return null;
    }

    public function db_get_query_rows($results, $query, $start = null)
    {
        $num_fields = mysqli_num_fields($results);
        $names = array();
        $types = array();
        for ($x = 0; $x < $num_fields; $x++) {
            $field = mysqli_fetch_field($results);
            $names[$x] = $field->name;
            $types[$x] = $field->type;
        }

        $out = array();
        $newrow = array();
        while (($row = mysqli_fetch_row($results)) !== null) {
            $j = 0;
            foreach ($row as $v) {
                $name = $names[$j];
                $type = $types[$j];

                if (($type === 'int') || ($type === 'integer') || ($type === 'real') || ($type === 1) || ($type === 3) || ($type === 8)) {
                    if ((($v === null)) || ($v === '')) { // Roadsend returns empty string instead of null
                        $newrow[$name] = null;
                    } else {
                        $_v = intval($v);
                        if (strval($_v) !== $v) {
                            $newrow[$name] = floatval($v);
                        } else {
                            $newrow[$name] = $_v;
                        }
                    }
                } elseif (($type === 16) || ($type === 'bit')) {
                    if ((strlen($v) === 1) && (ord($v[0]) <= 1)) {
                        $newrow[$name] = ord($v); // 0/1 char for BIT field
                    } else {
                        $newrow[$name] = intval($v);
                    }
                } else {
                    $newrow[$name] = $v;
                }

                $j++;
            }

            $out[] = $newrow;
        }
        mysqli_free_result($results);

        return $out;
    }
}