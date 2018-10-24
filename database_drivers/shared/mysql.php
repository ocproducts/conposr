<?php /*

 Conposr (Composr-lite framework for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    Conposr
 */

class Database_super_mysql
{
    public function apply_sql_limit_clause(&$query, $max = null, $start = 0)
    {
        if (($max !== null) && ($start !== null)) {
            $query .= ' LIMIT ' . strval($start) . ',' . strval($max);
        } elseif ($max !== null) {
            $query .= ' LIMIT ' . strval($max);
        } elseif ($start !== null) {
            $query .= ' LIMIT ' . strval($start) . ',30000000';
        }
    }

    public function db_get_first_id()
    {
        return 1;
    }

    public function db_string_equal_to($attribute, $compare)
    {
        return $attribute . "='" . db_escape_string($compare) . "'";
    }

    public function db_string_not_equal_to($attribute, $compare)
    {
        return $attribute . "<>'" . db_escape_string($compare) . "'";
    }

    public function db_empty_is_null()
    {
        return false;
    }

    public function db_encode_like($pattern)
    {
        return str_replace('\\\\_'/*MySQL escaped underscores*/, '\\_', $this->db_escape_string($pattern));
    }

    public function db_close_connections()
    {
        $this->cache_db = array();
        $this->last_select_db = null;
    }
}
