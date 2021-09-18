<?php
/*
 * Created       : 2020-06-05 09:58:19 pm
 * Author        : Gordon Lim <honwei189@gmail.com>
 * Last Modified : 2020-11-01 01:50:33 pm
 * Modified By   : Gordon Lim
 * ---------
 * Changelog
 *
 * Date & time           By                    Version   Comments
 * -------------------   -------------------   -------   ---------------------------------------------------------
 * 2020-11-01 01:42 pm   Gordon Lim            1.0.7     Added new feature - return SQL instead of send SQL to database
 * 2020-08-25 08:36 pm   Gordon Lim            1.0.6     Remove "SQL_CALC_FOUND_ROWS" because of it become deprecated function in
 *                                                       mySQL 8 and will be removed in the future
 *
 */

namespace honwei189\FDO\Factory;

use honwei189\Flayer\Core as flayer;

/**
 *
 * DB query function
 *
 *
 * @package     FDO
 * @subpackage
 * @author      Gordon Lim <honwei189@gmail.com>
 * @link        https://github.com/honwei189/fdo/
 * @version     "1.0.7" Added new feature - return SQL instead of send SQL to database
 * @since       "1.0.6" Remove "SQL_CALC_FOUND_ROWS" because of it become deprecated function in mySQL 8 and will be removed in the future
 */
trait QueryTrait
{
    public $_db;
    public $_table;
    public $_table_alias;
    public $_trx  = false;
    public $audit = false;
    public $affected_Rows;
    public $limit_data;
    public $num_rows;
    public $page_id;

    private $derived     = false;
    private $derived_sql = "";
    private $FDO;
    private $_count_group = false;
    private $_fetch_one   = false;
    private $_is_exists;
    private $_method          = [];
    private $_multi_log_table = false;
    private $_raws;
    private $_sql                    = "";
    private $_sql_without_limit      = "";
    private $_sql_display_num        = false;
    private $_sql_only               = false;
    private $_sql_without_select     = false;
    private $_table_alias_temp       = "";
    private $_table_cols             = null;
    private $_table_cols_nums        = 0;
    private $_table_join             = null;
    private $_table_joins            = null;
    private $_table_joins_table      = null;
    private $_table_left_joins       = null;
    private $_table_left_joins_table = null;
    private $_union                  = false;
    private $_union_pointer          = null;
    private $_union_tables           = null;
    private $_where                  = "";
    private $_group_by               = "";
    private $_limit                  = "";
    private $_max_by                 = "";
    private $_order_by               = "";

    /**
     * @access private
     * @internal
     */
    // public function __construct()
    // {
    //     // $this->FDO      = &$FDO;
    //     // $this->instance = $this->FDO->instance;
    // }

    // public function __get($arg)
    // {
    //     if (isset($this->_vars[$arg]) && str($this->_vars[$arg])) {
    //         return $this->_vars[$arg];
    //     }
    // }

    // public function __isset($name)
    // {
    //     return (isset($this->_vars[$name]) ? true : false);
    // }

    // public function __set($name, $val)
    // {
    //     unset($this->{"api_Key"});
    //     unset($this->{"token"});
    //     unset($this->{"key"});
    //     unset($this->{"file"});
    //     unset($this->{"id"});

    //     switch ($name) {
    //         case "api_Key":
    //         case "token":
    //         case "key":
    //         case "file":
    //         case "id":
    //             break;

    //         default:
    //             $this->_vars[$name] = $val;
    //             break;
    //     }
    // }

    // public function __unset($name)
    // {
    //     if (isset($this->_vars[$name])) {
    //         unset($this->_vars[$name]);
    //     }

    //     if (isset($this->_method[$name])) {
    //         unset($this->_method[$name]);
    //     }
    // }

    function  and ($column_name, $value = null) {
        if (is_null($value)) {
            $this->where($column_name);
        } else {
            $this->where("$column_name='$value'");
        }

        return $this;
    }

    /**
     * Create derived table.  It is currently attached to find() only
     *
     * Another derived function is $this->sub()
     *
     * e.g: $this->derived($this->abc()->where("id", 1)->find(), "t")->order_by("id", "asc")->find();
     *
     * @param \honwei189\FDO\SQL $object
     * @param string $name Optional.  Derived table name
     * @return \honwei189\FDO\SQL
     */
    public function derived(\honwei189\FDO\SQL $object, $name = null)
    {
        $this->derived = true;

        if (is_value($name)) {
            $this->set_table_temp_alias($name);
        }

        $this->derived_sql = $object->get_sql();

        return $this;
    }

    /**
     * Generate SQL where id = $id
     *
     * @param integer|string $id
     * @return FDO
     */
    public function by_id($id)
    {
        if (is_array($id)) {
            $id = array_map(
                function ($id) {
                    if (!is_numeric($id)) {
                        return (int) flayer::Crypto()->decrypt($id);
                    } else {
                        return (int) $id;
                    }
                },
                $id
            );
        }

        $prefix       = (str($this->_table_alias) ? $this->_table_alias : $this->_table) . ".";
        $this->_where = (str($this->_where) ? $this->_where . " and " : "") . (is_array($id) ? $prefix . "id in (" . join(", ", $id) . ")" : $prefix . "id = " . (int) $id);
        $this->_id    = $id;
        return $this;
    }

    /**
     * Declare table cols name
     *
     * Applicable for find(), findAll*(), get_all()
     *
     * Usages:
     *
     * e.g:
     *
     * $this->cols("id")->cols("name")->find();
     *
     * or;
     *
     * $this->cols("id");
     * $this->cols("name");
     * return $this->find();
     *
     * @param string|array $table_cols column name.  e.g:  id
     * @return FDO
     */
    public function cols($table_cols, $table_name = null)
    {
        $prefix = "";

        if ((is_array($this->_table_join) && count($this->_table_join) > 0) || (is_array($this->_table_joins) && count($this->_table_joins) > 0) || (is_array($this->_table_left_joins) && count($this->_table_left_joins) > 0)) {
            if (is_null($table_name)) {
                $prefix = (str($this->_table_alias) ? $this->_table_alias : $this->_table) . ".";
            } else {
                $prefix = $table_name . ".";
            }
        }

        if (is_array($table_cols)) {
            foreach ($table_cols as $k => $v) {
                if (strpos($v, ".") === false && strpos($v, "(") === false && strpos($v, "distinct") === false && strpos($v, "''") === false && substr($v, 0, 1) != "'") {
                    $table_cols[$k] = $prefix . trim($v);
                }
            }

            $this->_table_cols .= (str($this->_table_cols) ? ", " : "") . join(", ", $table_cols);
        } else {
            $_ = explode(",", $table_cols);
            foreach ($_ as $k => $v) {
                if (strpos($v, ".") === false && strpos($v, "(") === false && strpos($v, "distinct") === false && substr($v, 0, 1) != "'") {
                    $_[$k] = $prefix . trim($v);
                }
            }

            $table_cols = join(", ", $_);
            unset($_);

            $this->_table_cols .= (str($this->_table_cols) ? ", " : "") . (isset($this->_table_join[$table_name]) ? $this->_table_join[$table_name][1] . "." : "") . $table_cols;
        }

        return $this;
    }

    /**
     * Reset DB select COLUMNS from
     *
     * @return FDO
     */
    public function cols_clear()
    {
        $this->_table_cols = "";

        return $this;
    }

    /**
     * Count rows of table with certains criterias
     *
     * @param mixed $table_field_name Table field name.  Default is *.  e.g:  select count(*) from table, select count(id) from table
     * @return mixed
     */
    public function count_by($table_field_name = "*")
    {
        $this->_count_by = "count($table_field_name)";
        return $this;
    }

    /**
     * Count rows of table and group the table field with certains criterias
     *
     * @param mixed $table_field_name Table field name.  Default is *.  e.g:  select count(*) from table, select count(id) from table
     * @return mixed
     */
    public function count_group($table_field_name = "*")
    {
        $this->_count_group = true;
        $name               = $table_field_name;
        $table_field_name   = preg_replace("/as(.*)+$/si", "", $table_field_name);
        $this->_count_by    = "$name, count($table_field_name)";
        $this->_group_by    = " group by " . $table_field_name . " ";

        unset($name);
        return $this;
    }

    /**
     * Bulk apply SQL criteria -- where()
     *
     * example:
     *
     * $this->criterias($_POST);
     *
     * or;
     *
     * $this->criterias([
     *     "status"          => "C",
     *     "nums_data"       => $_SESSION['Preferences']['Data_Row'] ?? 200,
     *     "start_from_nums" => 1,
     *     "order_by"        => "created_at asc",
     *     "order_by"        => ["created_at", "asc"],
     *     "debug"           => true
     * ]);
     *
     * @param array $array
     * @return mixed
     */
    public function criterias($array)
    {
        if (is_array($array) && count($array) > 0) {
            foreach ($array as $k => $v) {
                if (!is_int($k)) {
                    if (!in_array($k, ["debug", "nums_data", "start_from_nums", "order_by", "group_by"])) {
                        $this->where($k, $v);
                    } else {
                        switch ($k) {
                            case "debug":
                                $this->debug((bool) $v);
                                break;

                            case "order_by":
                                if (is_array($v) && count($v) > 0) {
                                    $this->order_by($v[0], $v[1]);
                                } elseif (is_string($v)) {
                                    $this->order_by($v);
                                }
                                break;
                        }
                    }
                } else {
                    $this->where($v);
                }
            }

            if ($array["nums_data"] ?? false && $array["start_from_nums"] ?? false) {
                $this->limit($array["nums_data"], $array["start_from_nums"]);
            } else {
                if ($array["nums_data"] ?? false) {
                    $this->limit($array["nums_data"]);
                }

                if ($array["start_from_nums"] ?? false) {
                    $this->limit($array["start_from_nums"]);
                }
            }
        }
    }

    /**
     * Check the record exist.  If exist return TRUE, otherwise FALSE
     *
     * @return boolean
     */
    public function exist()
    {
        return ((double) $this->count_by()->get() > 0 ? true : false);
    }

    public function fetch($rs)
    {
        if (!empty($this->instance)) {
            return $rs->fetch();
        }
    }

    /**
     * Set PDO fetch mode
     *
     * @param string $mode Default is FETCH_INTO
     * @param string $class_name Default is fdoData.  You can use your custom class to fetch data from DB save into your class
     * @return FDO
     */
    public function fetch_mode($mode = \PDO::FETCH_LAZY, $class_name = "fdoData")
    {
        // if (str($class_name)){
        //     $rs->setFetchMode($mode, $class_name);
        // }
        if (!is_object($class_name)) {
            $class_name = __NAMESPACE__ . "\\" . $class_name;
        }

        $this->fetch_mode = (object) ["mode" => $mode, "class" => $class_name];

        return $this;
    }

    /**
     * Find data from $this->set_table()
     *
     * @param string $find_by_COLUMN_name Find compare with what table column.
     * e.g: id.  output: select id from table
     * e.g: id and name  output: select id, name from table where id = $data[] and name = $data[]
     * e.g: id or name  output: select id,name from table where id = $data[] or name = $data[]
     * e.g: id, name  output: select id,name from table where id = $data[] and name = $data[]
     *
     * @param string|integer $data e.g: $id = 1  $this->find("id", $id, "id, userid, name").  output: select id, userid, name from table where id = $id
     * @param string $select_cols_name e.g: "id, userid, name".  Default is select all cols
     * @return array
     */
    /**
     * Find data from $this->set_table()
     *
     * @param string $find_by_column_name Find compare with what table column.
     * e.g: id.  output: select id from table
     * e.g: id and name  output: select id, name from table where id = $data[] and name = $data[]
     * e.g: id or name  output: select id,name from table where id = $data[] or name = $data[]
     * e.g: id, name  output: select id,name from table where id = $data[] and name = $data[]
     *
     * @param string|integer $data e.g: $id = 1  $this->find("id", $id, "id, userid, name").  output: select id, userid, name from table where id = $id
     * @param string $select_cols_name e.g: "id, userid, name".  Default is select all cols
     * @return array
     */
    public function find($find_by_column_name = null, $data = null, $select_cols_name = "*")
    {
        $mode        = $this->fetch_mode->mode;
        $fetch_count = false;

        if (str($this->_count_by)) {
            $fetch_count   = true;
            $this->_limit  = "";
            $this->_max_by = "";
        }

        if ($this->_count_by) {
            $fetch_count = false;
        }

        if ($this->_union) {
            $_sql = null;

            $_sql[] = $this->gen_select_sql($find_by_column_name, $data, $select_cols_name)[0];

            foreach ($this->_union_tables as $k => $v) {
                $_sql[] = $v->gen_select_sql()[0];
            }

            if (is_array($_sql)) {
                // $stm = "select " . $this->_gen_select_cols($find_by_column_name, $select_cols_name)[0] . " from (\n (" . join(")\nunion\n (", $_sql) . ")\n) as " . $this->_table . $this->_group_by . $this->_order_by . $this->_limit;

                $stm = "select * from (\n (" . join(")\nunion\n (", $_sql) . ")\n) as " . $this->_table . $this->_group_by . $this->_order_by . $this->_limit;
            } else {
                return [];
            }

            unset($_sql);
            $this->_union_tables = null;
            $this->_union        = false;
        } else {
            $_   = $this->gen_select_sql($find_by_column_name, $data, $select_cols_name);
            $stm = $_[0];
            // $mode = $_[1];
        }

        // if (str($this->_limit)) {
        //     $stm = preg_replace("/^select/isU", "select SQL_CALC_FOUND_ROWS", $stm);
        //     $stm = preg_replace("/^\(select/isU", "(select SQL_CALC_FOUND_ROWS", $stm);
        // }

        unset($sql);
        unset($select_cols_name);
        unset($table);

        // $this->clear(false);

        if (str($this->_table_alias_temp)) {
            $this->_table_alias      = $this->_table_alias_temp;
            $this->_table_alias_temp = null;
        }

        $this->derived     = false;
        $this->derived_sql = "";

        if ($this->_get_sql) {
            return $stm;
        }

        if ($this->_debug_print) {
            $this->print_sql_format($stm);

            if (!$this->_soft_update) {
                exit;
            }
        } else {
            if ($this->_sql_only || $this->_sql_without_select) {
                $this->_sql_only = false;
                return $stm;
            } else {
                // $stm = $this->_sql;

                if ($this->is_enabled_query_log()) {
                    $this->write_audit_log(null, "Q", null, $this->_sql);
                }

                if ($fetch_count) {
                    return $this->read_one_sql($this->_sql, false, \PDO::FETCH_COLUMN)[0];
                } else {
                    if ($this->_fetch_one) {
                        $this->_fetch_one = false;
                        return $this->read_one_sql($this->_sql, false, $mode);
                    } else {
                        $stm = $this->_sql;
                        if ($this->_sql_display_num) {
                            $this->_sql_display_num = false;
                            $stm                    = $this->set_display_num($this->_sql);
                        }

                        return $this->read_all_sql($stm, false, $mode);
                    }
                }
            }
        }
    }

    /**
     * Find all data from $this->set_table() with what columns.
     *
     * e.g: $this->findAllByuserid("admin")
     *
     * findBy is prefix, the rest "userid" is what your column name.
     *
     * more example:
     *
     * findAllByname("Administrator"); // find data from table column name = name, where the name is "Administrator"
     *
     * findAllByemail("test@test.com"); // find data from table column name = email, where the email address is "test@test.com"
     *
     * @param string|null $find_by_column_name Find compare with what table column.  Allows null.  If null, then use $data as SQL where statement
     *
     * e.g: id.  output: select id from table
     * e.g: null.  output:  select $select_cols_name from table where $data
     *
     * @param string|integer $data e.g: $id = 1  $this->find("id", $id, "id, userid, name").  output: select id, userid, name from table where id = $id
     * @param string $select_cols_name e.g: "id, userid, name".  Default is select all cols
     * @return array
     */
    public function findAllBy($find_by_column_name, $data, $select_cols_name = "*")
    {
        if ($this->_set_encrypt_id) {
            $this->set_encrypt_id();
        }

        $fetch_count = false;

        if (str($this->_count_by)) {
            $fetch_count   = true;
            $this->_limit  = "";
            $this->_max_by = "";
        }

        if ($select_cols_name != "*" && str($this->_table_cols)) {
            $select_cols_name = "";
        }

        $stm = "select " . $this->_gen_select_cols($select_cols_name)[0] . " from " . $this->_table . (str($this->_table_alias) ? " as " . $this->_table_alias : "") . " where " . (is_null($find_by_column_name) ? "$data" : "$find_by_column_name = '$data'") . (str($this->_where) ? " and " . $this->_where : "") . $this->_group_by . $this->_order_by . $this->_limit;

        // if (str($this->_limit)) {
        // $stm                        = preg_replace("/^select/isU", "select SQL_CALC_FOUND_ROWS", $stm);
        // $stm                        = preg_replace("/^\(select/isU", "(select SQL_CALC_FOUND_ROWS", $stm);
        // $this->PageSeparator['SQL'] = $stm;
        // $_ = preg_replace("/join.*?(?=\\)|$)/mi", "", $stm);
        // preg_match("|select\s*(.*?)\s*from|siU", $_, $reg);

        // $this->_db->PageSeparator['SQL'] = $stm;

        // if (str($reg[1])) {
        //     $this->_db->PageSeparator['SQL'] = str_replace($reg[1], " count(*) as count ", $this->_db->PageSeparator['SQL']);
        // }

        // unset($reg);

        // $re = ["/ORDER BY.*?(?=\\)|$)/mi", '/.*(\limit\s+.*)/is'];

        // $this->_db->PageSeparator['SQL'] = preg_replace($re, "", $this->_db->PageSeparator['SQL']);
        // unset($re);
        // unset($_);
        // $this->_db->PageSeparator['SQL'] = $stm;
        // }

        $this->clear(false);

        if (str($this->_table_alias_temp)) {
            $this->_table_alias      = $this->_table_alias_temp;
            $this->_table_alias_temp = null;
        }

        if ($this->_get_sql) {
            return $stm;
        }

        if ($this->_debug_print) {
            $this->print_sql_format($stm);
            if (!$this->_soft_update) {
                exit;
            }
        } else {
            if ($this->_sql_only || $this->_sql_without_select) {
                $this->_sql_only = false;
                return $stm;
            } else {
                if ($this->is_enabled_query_log()) {
                    $this->write_audit_log(null, "Q", null, $stm);
                }

                if ($fetch_count) {
                    return $this->_receive_raws($this->read_one_sql($stm, \PDO::FETCH_COLUMN)[0]);
                } else {
                    if ($this->_sql_display_num) {
                        $this->_sql_display_num = false;
                        // $stm                    = "select ( @rownum := (@rownum + 1) ) as no, original_sql.* from ($stm) original_sql";

                        // $this->execute("SET @rownum := 0;");
                        preg_match("|select\s*(.*?)\s*from|isU", $stm, $reg);

                        $first_field = "";

                        if (str($reg[1])) {
                            $field = preg_split('|,|', $reg[1], -1, PREG_SPLIT_NO_EMPTY);

                            if (isa($field)) {
                                foreach ($field as $k => $v) {
                                    if (!preg_match("|\s*as\s*|", $v)) {
                                        $first_field = $v;
                                        break;
                                    }
                                }
                            }
                        }

                        if (str($first_field)) {
                            $stm = preg_replace("|^select|isU", "select row_number() over(order by $first_field desc) as no, ", $stm);
                        }

                        unset($first_field);
                        unset($reg);

                    }

                    return $this->_receive_raws($this->read_all_sql($stm, $this->fetch_mode->mode));
                }
            }
        }
    }

    /**
     * Find data from $this->set_table() with what columns.
     *
     * e.g: $this->findByuserid("admin")
     *
     * findBy is prefix, the rest "userid" is what your column name.
     *
     * more example:
     *
     * findByname("Administrator"); // find data from table column name = name, where the name is "Administrator"
     *
     * findByemail("test@test.com"); // find data from table column name = email, where the email address is "test@test.com"
     *
     * @param string|null $find_by_column_name Find compare with what table column.  Allows null.  If null, then use $data as SQL where statement
     *
     * e.g: id.  output: select id from table
     * e.g: null.  output:  select $select_cols_name from table where $data
     *
     * @param string|integer $data e.g: $id = 1  $this->find("id", $id, "id, userid, name").  output: select id, userid, name from table where id = $id
     * @param string $select_cols_name e.g: "id, userid, name".  Default is select all cols
     * @return array
     */
    public function findBy($find_by_column_name, $data, $select_cols_name = "*", $print_sql = false)
    {
        $fetch_count = false;
        $debug       = $this->_debug_print;

        if (str($this->_count_by)) {
            $fetch_count = true;
        }

        if ($select_cols_name != "*" && str($this->_table_cols)) {
            $select_cols_name = "";
        }

        if ($print_sql) {
            $debug = false;
        }

        if (is_null($find_by_column_name) && !str($data)) {
            return false;
        }

        $sql = "select " . $this->_gen_select_cols($select_cols_name)[0] . " from " . $this->_table . (str($this->_table_alias) ? " as " . $this->_table_alias : "") . " where " . (is_null($find_by_column_name) ? "$data" : "$find_by_column_name = '$data'");

        $this->clear(false);

        if (str($this->_table_alias_temp)) {
            $this->_table_alias      = $this->_table_alias_temp;
            $this->_table_alias_temp = null;
        }

        if ($this->_get_sql) {
            return $sql;
        }

        if ($debug) {
            $this->print_sql_format($sql);
            if (!$this->_soft_update) {
                exit;
            }
        } else {

            if ($print_sql) {
                $this->print_sql_format($sql);
            }

            if ($this->is_enabled_query_log()) {
                $this->write_audit_log(null, "Q", null, $sql);
            }

            if ($fetch_count) {
                return $this->_receive_raws($this->read_one_sql($sql, false, \PDO::FETCH_COLUMN)[0]);
            } else {
                return $this->_receive_raws($this->read_one_sql($sql, false, $this->fetch_mode->mode));
            }
        }
    }

    /**
     * Find data from $this->set_table() with id
     *
     * e.g: $this->findById(1); // find data from table column name = id, where the id is "1"
     *
     *
     * @param integer $data e.g: $id = 1  $this->find("id", $id, "id, userid, name").  output: select id, userid, name from table where id = $id
     * @param string $select_cols_name e.g: "id, userid, name".  Default is select all cols
     * @return array
     */
    public function findById($id, $select_cols_name = "*")
    {
        $id = $this->get_id($id);

        $fetch_count = false;

        if (str($this->_count_by)) {
            $fetch_count   = true;
            $this->_limit  = "";
            $this->_max_by = "";

        }

        if ($select_cols_name != "*" && str($this->_table_cols)) {
            $select_cols_name = "";
        }

        if ($this->_union) {
            $_sql = null;

            foreach ($this->_union_tables as $k => $v) {
                $_sql[] = $v->gen_select_sql()[0];
            }

            if (is_array($_sql)) {
                $sql = "select " . $this->_gen_select_cols($select_cols_name)[0] . " from (\n (" . join(")\nunion\n (", $_sql) . ")\n) as " . $this->_table . $this->_group_by . $this->_order_by . $this->_limit;
            } else {
                return [];
            }

            unset($_sql);
            $this->_union_tables = null;
            $this->_union        = false;
        } else {
            $_    = $this->gen_select_sql($select_cols_name, $id, $select_cols_name);
            $sql  = $_[0];
            $mode = $_[1];
        }

        $sql .= (str($this->_where) || str($this->_table_join) || str($this->_table_joins) ? " and " : " where ") . (str($this->_table_alias) ? $this->_table_alias . "." : "") . "id = " . (int) $id;

        $this->clear(false);

        if (str($this->_table_alias_temp)) {
            $this->_table_alias      = $this->_table_alias_temp;
            $this->_table_alias_temp = null;
        }

        if ($this->_get_sql) {
            return $sql;
        }

        if ($this->_debug_print) {
            $this->print_sql_format($sql);
            if (!$this->_soft_update) {
                exit;
            }
        } else {
            if ($this->is_enabled_query_log()) {
                $this->write_audit_log(null, "Q", null, $sql);
            }

            if ($fetch_count) {
                return $this->_receive_raws($this->read_one_sql($sql, false, \PDO::FETCH_COLUMN)[0]);
            } else {
                return $this->_receive_raws($this->read_one_sql($sql, false, $this->fetch_mode->mode));
            }
        }
    }

    /**
     * Alternative way to retrieve any table's single row data
     *
     * Usage :
     *
     * $this->get("my_table", "id, name", $id, "user_uid");
     *
     * or
     *
     * $this->where("id !=1 ")->get(); //get all table cols data
     *
     * or;
     *
     * $this->where("id !=1 ")->get("id, name"); //get id and name from table only
     *
     *
     * @param string $table Data table name
     * @param string $table_cols What table column you want to fetch.  e.g:  $table_cols = "name,email"  output: select name, email from tests where $query_by = $id
     * @param string|integer $id ID.  Allows to add more than one.  e.g:  $id = "1 and date=current_date".  output: select abc from tests where $query_by = 1 and date=current_date
     * @param string $query_by Default is id.  Related to $id.  e.g output: select abc from tests where $query_by = $id
     * @param integer $column_num Depend on $table_cols.  If more than one, 0 = first column, 1 = second column.  If null, fetch all data with $table_cols as array mode
     * @return array|string
     */
    public function get($table = null, $table_cols = null, $id = null, $query_by = "id", $column_num = null)
    {
        if (str($this->_where) || str($this->_table_cols) || is_null($table_cols)) {
            $col   = null;
            $count = false;

            if (str($this->_count_by)) {
                $count = true;
            }

            list($sql, $mode) = $this->gen_select_sql($table, $id, $table_cols);

            if ($this->_table_cols_nums < 2) {
                $count = true;
            }

            $this->_where                  = "";
            $this->_count_by               = "";
            $this->_count_group            = false;
            $this->_order_by               = "";
            $this->_group_by               = "";
            $this->_limit                  = "";
            $this->_max_by                 = "";
            $this->_sql                    = "";
            $this->_table_cols             = "";
            $this->_table_cols_nums        = 0;
            $this->_table_join             = null;
            $this->_table_joins            = null;
            $this->_table_joins_table      = null;
            $this->_table_left_joins       = null;
            $this->_table_left_joins_table = null;

            if (str($this->_table_alias_temp)) {
                $this->_table_alias      = $this->_table_alias_temp;
                $this->_table_alias_temp = null;
            }

            if ($this->_get_sql) {
                return $sql;
            }

            if ($this->_debug_print) {
                $this->print_sql_format($sql);
                if (!$this->_soft_update) {
                    exit;
                }
            } else {
                // if ($this->is_enabled_query_log()) {
                //     $this->write_audit_log(null, "Q", null, $sql);
                // }

                if ($count) {
                    return $this->read_one_sql($sql, false, \PDO::FETCH_COLUMN, 0);
                } else {
                    return $this->read_one_sql($sql, false, $this->fetch_mode->mode);
                }

            }
        } else {
            if (substr($id, 0, 1) != "'" && substr($id, -1, 1) != "'") {
                $id = "'$id'";
            }

            $sql = "select $table_cols from $table where $query_by = $id'";

            // if ($this->is_enabled_query_log()) {
            //     $this->write_audit_log(null, "Q", null, $sql);
            // }

            return $this->read_one_sql($sql, false);
        }
    }

    /**
     * Alternative way to retrieve any table's all data
     *
     * @param string $table Data table name
     * @param string $table_cols What table column you want to fetch.  e.g:  $table_cols = "name,email"  output: select name, email from tests where $query_by = $id
     * @param string|integer $id ID.  Allows to add more than one.  e.g:  $id = "1 and date=current_date".  output: select abc from tests where $query_by = 1 and date=current_date
     * @param string $query_by Default is id.  Related to $id.  e.g output: select abc from tests where $query_by = $id
     * @param integer $column_num Depend on $table_cols.  If more than one, 0 = first column, 1 = second column.  If null, fetch all data with $table_cols as array mode
     * @return array|string
     */
    public function get_all($table, $table_cols, $id = null, $query_by = "id", $column_num = null)
    {
        if ($this->_set_encrypt_id) {
            $this->set_encrypt_id();
        }

        $mode = null;

        if (str($this->_where)) {
            list($sql, $mode)              = $this->gen_select_sql($table, $id, $table_cols);
            $this->_where                  = "";
            $this->_limit                  = "";
            $this->_max_by                 = "";
            $this->_sql                    = "";
            $this->_table_cols             = "";
            $this->_table_cols_nums        = 0;
            $this->_table_join             = null;
            $this->_table_joins            = null;
            $this->_table_joins_table      = null;
            $this->_table_left_joins       = null;
            $this->_table_left_joins_table = null;

            if (!$this->_sql_display_num) {
                $this->_order_by = "";
                $this->_group_by = "";
            }

            if (str($this->_table_alias_temp)) {
                $this->_table_alias      = $this->_table_alias_temp;
                $this->_table_alias_temp = null;
            }

            if (!is_null($column_num)) {
                $mode = \PDO::FETCH_COLUMN;
            } else {
                $mode = \PDO::FETCH_INTO;
            }

            if ($this->_get_sql) {
                return $sql;
            }

            if ($this->_debug_print) {
                $this->print_sql_format($sql);
                if (!$this->_soft_update) {
                    exit;
                }
            } else {
                if ($this->is_enabled_query_log()) {
                    $this->write_audit_log(null, "Q", null, $sql);
                }

                return $this->read_all_sql($sql, false, $mode, $column_num);
            }
        } else {
            if (substr($id, 0, 1) != "'" && substr($id, -1, 1) != "'") {
                $id = "'$id'";
            }

            $sql = "select $table_cols from $table where $query_by = $id";

            if ($this->_get_sql) {
                return $sql;
            }

            if ($this->is_enabled_query_log()) {
                $this->write_audit_log(null, "Q", null, $sql);
            }

            return $this->read_all_sql($sql, false, $mode, $column_num);
        }
    }

    public function get_cols($table, $type = "string")
    {
        $q = $this->instance->prepare("DESCRIBE $table");
        $q->execute();
        $table_fields = $q->fetchAll(\PDO::FETCH_COLUMN);

        $this->free($q);
        unset($q);

        if ($type == "string") {
            echo join(", ", $table_fields);
        } else {
            return $table_fields;
        }
    }

    /**
     * Get previous declare count()
     *
     * e.g:
     *
     * $this->count("id");
     * echo $this->get_count_field_name(); // output = id
     *
     *
     * $this->count("name");
     * echo $this->get_count_field_name(); // output = name
     *
     * @return string
     */
    public function get_count_field_name()
    {
        return trim(preg_replace("|count\((.*)\)|", "\\1", $this->_count_by));
    }

    /**
     * Return SQL
     *
     */
    public function get_sql()
    {
        $this->_sql_only = true;
        return $this->find();
    }

    /**
     * Get declared database table
     */
    public function get_table()
    {
        return $this->_table;
    }

    /**
     * Group by
     *
     * @param string $string Table column would like to group
     * @return FDO
     */
    public function group_by($string)
    {
        $this->_group_by = " group by " . $string . " ";
        return $this;
    }

    /**
     * Declare inner joins table
     *
     * Usage:
     *
     * $this->users()->inner_join("users_roles", $this->users()->_table, "b", "users.org_Usrgrp = b.id");
     * $this->users()->cols("id, name");
     * $this->users()->inner_join("name as role", "b");
     * $this->users()->inner_join("privileges", "b");
     * pre($this->users()->find());
     *
     * @param string $table_name Table name
     * @param string $join_to_table Join with what table's name
     * @param string $alias_name Rename the table.  e.g:  $alias_name = "a"   output: $table_name as a
     * @return FDO
     */
    public function inner_join($table_name, $join_to_table, $alias_name, $join_conditions)
    {
        $alias = (str($alias_name) ? $alias_name : $table_name);

        $this->_table_left_joins[$join_to_table][$alias] = " inner join $table_name as $alias_name " . (is_array($join_conditions) && count($join_conditions) > 0 ? join(" and ", $join_conditions) : " ON ($join_conditions)");
        $this->_table_left_joins_table[$alias]           = (str($alias_name) ? $alias_name : $table_name);

        return $this;
    }

    /**
     * Declare join table
     *
     * e.g:  select a* from table_a, table b where a.id = b.id
     *
     * Usages:
     *
     * $this->users()->join("users_roles", "users.org_Usrgrp = b.id", "b");
     * // $this->users()->cols("id, name");
     * $this->users()->cols(["id", "name"]);
     * $this->users()->cols("name as role", "users_roles");
     *
     * @param string $table_name Table name
     * @param mixed $alias_name Rename the table.  e.g:  $alias_name = "a"   output: $table_name as a
     * @return FDO
     */
    public function join($table_name, $join_coditions, $alias_name = null)
    {
        $this->_table_join[$table_name] = [(str($alias_name) ? $table_name . " as " . $alias_name : $table_name . " as " . $table_name), (str($alias_name) ? $alias_name : $table_name), $join_coditions];

        return $this;
    }

    /**
     * Declare join table's columns's name
     *
     * Applicable for find(), findAll*(), get_all()
     *
     * Usages:
     *
     * e.g:
     *
     * $this->join_cols("id")->join_cols("name")->find();
     *
     * or;
     *
     * $this->join_cols("id", "table");
     * $this->join_cols("name", "table");
     * $this->join("table", "b", "b.userid=users.userid")  //output : select b.id, b.name from users as users left join table as b ON (b.userid=users.userid)
     * return $this->find();
     *
     *
     * @param mixed $table_cols
     * @param string $table_name
     * @return FDO
     */
    public function join_cols($table_cols, $table_name)
    {
        if (isset($this->_table_join[$table_name])) {
            if (is_array($table_cols)) {
                $this->_table_cols .= (str($this->_table_cols) ? ", " : "") . join(", ", $table_cols);
            } else {
                $this->_table_cols .= (str($this->_table_cols) ? ", " : "") . $table_cols;
            }
        } else {
            foreach ($this->_table_join as $k => $v) {
                if (isset($v[1]) && $v[1] == $table_name) {
                    if (is_array($table_cols)) {
                        $this->_table_cols .= (str($this->_table_cols) ? ", " : "") . join(", " . $table_name . ".", $table_cols);
                    } else {
                        $this->_table_cols .= (str($this->_table_cols) ? ", " : "") . $table_name . "." . $table_cols;
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Declare join table.
     *
     * e.g:  select a.* from table_a join table_b as b ON (a.id = b.id)
     *
     * Usage:
     *
     * $this->users()->joins("users_roles", $this->users()->_table, "b", "users.org_Usrgrp = b.id");
     * $this->users()->cols("id, name", "b");
     * pre($this->users()->find());
     *
     * @param string $table_name Table name
     * @param string $join_to_table Join with what table's name
     * @param string $alias_name Rename the table.  e.g:  $alias_name = "a"   output: $table_name as a
     * @return FDO
     */
    public function joins($table_name, $join_to_table, $alias_name, $join_coditions)
    {
        $alias = (str($alias_name) ? $alias_name : $table_name);

        $this->_table_joins[$join_to_table][$alias] = " join $table_name as $alias_name ON ($join_coditions)";
        $this->_table_joins_table[$alias]           = (str($alias_name) ? $alias_name : $table_name);

        return $this;
    }

    /**
     * Declare left join's table column's name
     *
     * Applicable for find(), findAll*(), get_all()
     *
     * Usages:
     *
     * e.g:
     *
     * $this->left_cols("id")->left_cols("name")->find();
     *
     * or;
     *
     * $this->left_join("table", "users", "b", "b.userid=users.userid")  //output : select b.id, b.name from users as users left join table as b ON (b.userid=users.userid)
     * $this->left_cols("id", "table");
     * $this->left_cols("name", "table");
     * return $this->find();
     *
     * @param mixed $table_cols
     * @param string $table_name
     * @return FDO
     */
    public function left_cols($table_cols, $table_name)
    {
        if (isset($this->_table_left_joins_table[$table_name])) {
            if (is_array($table_cols)) {
                foreach ($table_cols as $k => $v) {
                    $table_cols[$k] = $table_name . "." . trim($v);
                }

                $this->_table_cols .= (str($this->_table_cols) ? ", " : "") . join(", ", $table_cols);
            } else {
                $_ = explode(",", $table_cols);
                foreach ($_ as $k => $v) {
                    $_[$k] = $table_name . "." . trim($v);
                }

                $table_cols = join(", ", $_);
                unset($_);

                $this->_table_cols .= (str($this->_table_cols) ? ", " : "") . $table_cols;
            }
        }

        return $this;
    }

    /**
     * Declare left joins table
     *
     * Usage:
     *
     * $this->users()->left_join("users_roles", $this->users()->_table, "b", "users.org_Usrgrp = b.id");
     * $this->users()->cols("id, name");
     * $this->users()->left_cols("name as role", "b");
     * $this->users()->left_cols("privileges", "b");
     * pre($this->users()->find());
     *
     * @param string $table_name Table name
     * @param string $join_to_table Join with what table's name
     * @param string $alias_name Rename the table.  e.g:  $alias_name = "a"   output: $table_name as a
     * @return FDO
     */
    public function left_join($table_name, $join_to_table, $alias_name, $join_conditions)
    {
        $alias = (str($alias_name) ? $alias_name : $table_name);

        $this->_table_left_joins[$join_to_table][$alias] = " left join $table_name as $alias_name " . (is_array($join_conditions) && count($join_conditions) > 0 ? join(" and ", $join_conditions) : " ON ($join_conditions)");
        $this->_table_left_joins_table[$alias]           = (str($alias_name) ? $alias_name : $table_name);

        return $this;
    }

    /**
     * Declare left outer joins table
     *
     * Usage:
     *
     * $this->users()->left_outer_join("users_roles", $this->users()->_table, "b", "users.org_Usrgrp = b.id");
     * $this->users()->cols("id, name");
     * $this->users()->left_outer_join("name as role", "b");
     * $this->users()->left_outer_join("privileges", "b");
     * pre($this->users()->find());
     *
     * @param string $table_name Table name
     * @param string $join_to_table Join with what table's name
     * @param string $alias_name Rename the table.  e.g:  $alias_name = "a"   output: $table_name as a
     * @return FDO
     */
    public function left_outer_join($table_name, $join_to_table, $alias_name, $join_conditions)
    {
        $alias = (str($alias_name) ? $alias_name : $table_name);

        $this->_table_left_joins[$join_to_table][$alias] = " left outer join $table_name as $alias_name " . (is_array($join_conditions) && count($join_conditions) > 0 ? join(" and ", $join_conditions) : " ON ($join_conditions)");
        $this->_table_left_joins_table[$alias]           = (str($alias_name) ? $alias_name : $table_name);

        return $this;
    }

    /**
     * SQL where LIKE statement
     *
     * example :
     *
     * $this->like("name", "abc"); //output select * from abc where trim(lower(name)) like '%abc%';
     * $this->like(["name", "telno"], "abc"); //output select * from abc where ( trim(lower(name)) like '%abc%' or trim(lower(telno)) like '%abc%');
     * $this->like("abc", ["name", "telno"]); //output select * from abc where ( trim(lower(abc)) like '%name%' or trim(lower(abc)) like '%telno%');
     *
     * @param string|array $column_name
     * @param string $value
     * @return FDO
     */
    public function like($column_name, $value)
    {
        $sql = "";
        if (is_array($column_name)) {
            $_ = [];
            foreach ($column_name as $k => $v) {
                $_[] = "trim(lower($v)) like '%" . trim(strtolower($value)) . "%'";
            }

            $sql = "(" . join(" or ", $_) . ")";
            unset($_);
        } else {
            if (is_array($value)) {
                $_ = [];
                foreach ($value as $k => $v) {
                    $_[] = "trim(lower($column_name)) like '%" . trim(strtolower($v)) . "%'";
                }

                $sql = "(" . join(" or ", $_) . ")";
                unset($_);

            } else {
                $sql = "trim(lower($column_name)) like '%" . trim(strtolower($value)) . "%'";
            }
        }

        return $this->where($sql);
    }

    /**
     * Limit numbers of rows data
     *
     * @param mixed $get_nums_of_data To fetch how many data per once.  mySQL support max unsigned big integer is 18446744073709551615, whereas PHP 32bit supported max size is 2147483647, 64bit is 9223372036854775807
     *
     * You can also passing $get_nums_of_data as array["start_from_nums" => 1, "nums_data" => 20]
     *
     * @param mixed $start_from_nums Get start from which row of data.  default is 0 = First row
     * @return FDO
     */
    public function limit($get_nums_of_data = 2147483647, $start_from_nums = 0)
    {
        if (is_array($get_nums_of_data)) {
            $data = $get_nums_of_data;
            unset($get_nums_of_data);
            $get_nums_of_data = 100;
            $start_from_nums  = 0;

            if (isset($data['start_from_nums']) && isset($data['nums_data']) && str($data['start_from_nums']) && str($data['nums_data'])) {
                $get_nums_of_data = (int) $data['nums_data'];
                $start_from_nums  = ((int) $data['start_from_nums'] - 1);
            } else {
                if (isset($data['nums_data']) && str($data['nums_data'])) {
                    $get_nums_of_data = (int) $data['nums_data'];
                }
            }

            unset($data);
        } else {
            if (is_null($get_nums_of_data)) {
                $get_nums_of_data = 2147483647;
            }
        }

        if (!isset($_GET['p']) && !str($_GET['p'])) {
            $this->page_id    = 1;
            $this->limit_data = 0;
        } else {
            $this->page_id    = $_GET['p'];
            $this->limit_data = ($_GET['p'] * $get_nums_of_data) - $get_nums_of_data;
            $start_from_nums  = $this->limit_data;
        }

        $this->_limit = " limit $start_from_nums, $get_nums_of_data";
        return $this;
    }

    /**
     * Limit numbers of rows data with SQL
     *
     * @param mixed $get_nums_of_data To fetch how many data per once.  mySQL support max unsigned big integer is 18446744073709551615, whereas PHP 32bit supported max size is 2147483647, 64bit is 9223372036854775807
     *
     * You can also passing $get_nums_of_data as array["start_from_nums" => 1, "nums_data" => 20]
     *
     * @param mixed $start_from_nums Get start from which row of data.  default is 0 = First row
     * @return FDO
     */
    public function limit_sql($sql, $nums_data_to_fetch = 10)
    {
        if (!isset($_GET['p_id']) && !str($_GET['p_id'])) {
            $this->page_id    = 1;
            $this->limit_data = 0;
        } else {
            $this->page_id    = $_GET['p_id'];
            $this->limit_data = ($_GET['p_id'] * $nums_data_to_fetch) - $nums_data_to_fetch;
        }

        // $sql = preg_replace("/select/i", "Select SQL_CALC_FOUND_ROWS", $sql, 1);

        return $sql . " limit " . $this->limit_data . ",$nums_data_to_fetch";
    }

    /**
     * Get max number from table rows with certains criterias
     *
     * @param mixed $table_field_name Table field name.  Default is *.  e.g:  select count(*) from table, select count(id) from table
     * @return mixed
     */
    public function max_by($table_field_name = "*")
    {
        $this->_max_by = "max($table_field_name)";
        return $this;
    }

    /**
     * SQL where NOT statement
     *
     * example :
     *
     * $this->not("name", "abc"); //output select * from abc where name != 'abc';
     * $this->not(["name", "telno"], "abc"); //output select * from abc where ( name != '%abc%' and telno != 'abc');
     *
     * @param string|array $column_name
     * @param string $value
     * @return db_helper
     */
    public function not($column_name, $value)
    {
        $sql = "";
        if (is_array($column_name)) {
            $_ = [];
            foreach ($column_name as $k => $v) {
                $_[] = "$v != '" . trim($value) . "'";
            }

            $sql = "(" . join(" and ", $_) . ")";
            unset($_);
        } else {
            $sql = "$column_name != '" . trim($value) . "'";
        }

        return $this->where($sql);
    }

    /**
     * SQL where OR statement
     *
     * example :
     *
     * $this->or("name", "abc"); //output select * from abc where id = 1 or ( trim(lower(name)) = 'abc' );
     * $this->or(["name", "telno"], "abc"); //output select * from abc where id = 1 and ( trim(lower(name)) = 'abc' or trim(lower(telno)) = 'abc');
     * $this->or("abc", ["name", "telno"]); //output select * from abc where id = 1 and ( trim(lower(abc)) = 'name' or trim(lower(abc)) = 'telno');
     *
     * @param string|array $column_name
     * @param string $value
     * @return FDO
     */
    function  or ($column_name, $value) {
        $sql = "";
        if (is_array($column_name)) {
            $_ = [];
            foreach ($column_name as $k => $v) {
                $_[] = "trim(lower($v)) = '" . trim(strtolower($value)) . "'";
            }

            $sql = "(" . join(" or ", $_) . ")";
            unset($_);

            return $this->where($sql);
        } else {
            if (is_array($value)) {
                $_ = [];
                foreach ($value as $k => $v) {
                    $_[] = "trim(lower($column_name)) = '" . trim(strtolower($v)) . "'";
                }

                $sql = "(" . join(" or ", $_) . ")";
                unset($_);

                return $this->where($sql);
            } else {
                $this->_where .= (str($this->_where) ? " or " : " ") . "trim(lower($column_name)) = '" . trim(strtolower($value)) . "'";
            }
        }

        return $this;
    }

    /**
     * Set SQL ordering sequence.  Applicable for find(), get_all(), data_all()
     *
     * @param string $sorting_cols cols name and sorting sequence.  e.g:  name asc, date desc
     * @param string $ordering_sequence ASC or DESC
     * @return FDO
     */
    public function order_by($sorting_cols, $ordering_sequence = "")
    {
        if (str($this->_order_by)) {
            $this->_order_by .= ", $sorting_cols $ordering_sequence";
        } else {
            $this->_order_by = " order by $sorting_cols $ordering_sequence";
        }

        return $this;
    }

    /**
     * Generate SQL OR statement for form input
     *
     * Usage :
     *
     * $this->or_input("form_object_name", "db_table_column_name", "like"); // output = select * from example where db_table_column name like '%form_object_value%'
     *
     * or;
     *
     * $this->or_input("form_object_name", [
     *     "(select id from example_b where name like '%{form_object_name}%')",
     *     "{form_object_name}",
     * ], "like");
     *
     * // output = select * from example where
     *                          db_table_column_name in (select id from example_b where name like '%form_object_value%')
     *                          and db_table_column_name = 'form_object_value'
     *
     *
     * or;
     *
     * $this->or_input("", [
     *     "ANY_COLUMN_NAME_YOU_LIKE in (select id from example_b where name like '%{form_object_name}%')",
     *     "ANY_COLUMN_NAME_YOU_LIKE = '{form_object_name}'",
     * ], "like");
     *
     * // output = select * from example where
     *                          ANY_COLUMN_NAME_YOU_LIKE in (select id from example_b where name like '%form_object_value%')
     *                          and ANY_COLUMN_NAME_YOU_LIKE = 'form_object_value'
     *
     * @param string $input_key_name
     * @param string|array $column_name
     * @param string $type
     * @return FDO
     */
    public function or_input($input_key_name, $column_name, $type = "")
    {
        $value = "";

        // if (isset($this->_post[$input_key_name])) {
        //     $value = $this->_post[$input_key_name];
        // } else if (isset($this->_get[$input_key_name])) {
        //     $value = $this->_get[$input_key_name];
        // }

        if (is_array($column_name) && count($column_name) > 0) {
            if (str($input_key_name)) {
                $value = $this->value_format($this->_request[$input_key_name], $type);
                $value = str_replace("{$input_key_name}", $this->_request[$input_key_name], $value);

                if ($type == "like") {
                    $this->like($column_name, $value);
                } else {
                    $this->or($column_name, $value);
                }
            }
        } else {
            if (str($input_key_name)) {
                $value = $this->value_format($this->inputs($input_key_name, $type), $type);

                if (isset($this->_post[$input_key_name])) {
                    $value = str_replace("{$input_key_name}", $this->_request[$input_key_name], $value);
                }

                if (str($value)) {
                    // $value = $this->_post_value($value, $type);

                    if ($type == "like") {
                        $this->where("trim(lower($column_name)) like '%" . trim(strtolower($value)) . "%'");
                    } else {
                        $this->where("$column_name='$value'");
                    }
                }

                unset($value);
            } else {
                $value = $this->value_format($column_name, $type);
                preg_match("/\{(.*?)\}/si", $value, $reg);

                if (is_array($reg) && count($reg) > 1) {
                    if (isset($this->_post[$reg[1]]) && str($this->_post[$reg[1]])) {
                        $value = str_replace("{" . $reg[1] . "}", $this->_post[$reg[1]], $value);
                    } else {
                        $value = "";
                    }
                }

                $this->where($value);

                unset($reg);
                unset($value);
            }
        }

        return $this;
    }

    /**
     * Passthrough save(), store() and update().
     *
     * To not execute SQL into DB
     *
     * @param bool $bool
     */
    public function passthrough($bool = true)
    {
        $this->_passthrough = (bool) $bool;
    }

    public function q($sql)
    {
        if ($this->is_connected()) {
            return $this->instance->query($sql);
        }
    }

    public function query($sql)
    {
        if (!empty($this->instance)) {
            try {
                $rs = $this->instance->prepare($sql);
                $rs->execute();
                $Count = $rs->rowCount();
                $this->error($rs);

                $rs->closeCursor();

                unset($rs);

                return $Count;
            } catch (\PDOException $e) {
                $this->error($rs);

                ob_start();
                print_r($e->errorInfo);
                $except = new \Exception;
                print_r($except->getTraceAsString());
                $error = ob_get_contents();
                ob_end_clean();

                $this->write_exceptional($sql, $e->getMessage(), $error);
                $this->error($rs);
                unset($error);
                unset($except);
            }

        }
    }

    /**
     * Read one data from DB
     *
     * @return array
     */
    public function read($id = null)
    {
        $this->_fetch_one = true;

        if (isset($id) && str($id)) {
            return $this->by_id($id)->find();
        } else {
            return $this->find();
        }
    }

    /**
     * Read all data from DB
     *
     * @return array
     */
    public function read_all($sql_where = null)
    {
        return $this->find($sql_where);
    }

    /**
     * Fetch all data from DB with SQL
     *
     * @param string $sql
     * @param boolean $audit
     * @param integer $mode
     * @param integer $column_num
     * @return array
     */
    public function read_all_sql($sql, $audit = false, $mode = \PDO::FETCH_INTO, $column_num = null)
    {
        $this->_set_sql_to_sub = false;
        $this->_sql            = "";
        if (!empty($this->instance)) {
            $this->action_type = "Q";

            try {
                // if ($audit) {
                //     $this->to_audit("select", $sql);
                // }
                $rs = null;

                try {
                    if ($this->_logger) {
                        if (stripos($sql, "SELECT LAST_INSERT_ID") !== true) {
                            $this->write_audit_log(null, "Q", null, $sql);
                            $this->_logger = false;
                        }
                    }

                    $rs                  = $this->instance->prepare($sql);
                    $this->affected_Rows = $rs->execute();

                    if ($mode == \PDO::FETCH_INTO) {
                        $rs->setFetchMode($mode, (is_object($this->fetch_mode->class) ? $this->fetch_mode->class : new $this->fetch_mode->class)); //FETCH_ROW
                    } else {
                        $rs->setFetchMode($mode); //FETCH_ROW
                    }

                    $this->error($rs);
                } catch (\PDOException $e) {
                    ob_start();
                    print_r($e->errorInfo);
                    $except = new \Exception;
                    print_r($except->getTraceAsString());
                    $error = ob_get_contents();
                    ob_end_clean();

                    $this->write_exceptional($sql, $e->getMessage(), $error);
                    $this->error($rs);
                    unset($error);
                    unset($except);
                }

                if (!is_null($rs)) {
                    if ($this->_set_encrypt_id) {
                        if ($mode == \PDO::FETCH_INTO || $mode == \PDO::FETCH_LAZY) {
                            $vars = null;
                            $data = [];
                            $i    = 0;

                            if ($mode == \PDO::FETCH_LAZY) {
                                while ($rows = $rs->fetch()) {
                                    $data[$i] = (array) $rows;
                                    unset($data[$i]['queryString']);

                                    foreach ($data[$i] as $k => $v) {
                                        if (stripos($k, "_id") !== false || (string) $k == "id" || substr($k, -2) == "id") {
                                            $data[$i][$k] = trim(flayer::Crypto()->encrypt($v));
                                        }
                                    }

                                    ++$i;
                                }
                            } else {
                                while ($rows = $rs->fetch()) {
                                    $vars     = (array) $rows;
                                    $data[$i] = clone $rows;

                                    foreach ($vars as $k => $v) {
                                        if (stripos($k, "_id") !== false || (string) $k == "id" || substr($k, -2) == "id") {
                                            $data[$i]->$k = trim(flayer::Crypto()->encrypt($v));
                                        }
                                    }
                                    ++$i;
                                }
                            }

                            unset($vars);
                            unset($i);
                            return $data;
                        } else {
                            if (!is_null($column_num)) {
                                $data = $rs->fetchAll($mode, $column_num);
                            } else {
                                $data = $rs->fetchAll($mode);
                            }

                            $count = $rs->rowCount();

                            if ($count > 0) {
                                if (is_multi_array($data)) {
                                    for ($i = 0; $i < $count; ++$i) {
                                        // $data[$i]['id'] = trim(flayer::Crypto()->encrypt($data[$i]['id']));
                                        $x = 0;
                                        foreach ($data[$i] as $k => $v) {
                                            if (stripos($k, "_id") !== false || (string) $k == "id" || substr($k, -2) == "id") {
                                                $data[$i][$k] = trim(flayer::Crypto()->encrypt($data[$i][$k]));

                                                if ($mode == \PDO::FETCH_BOTH) {
                                                    $data[$i][$x] = $data[$i][$k];
                                                }
                                            }
                                            $x++;
                                        }
                                    }
                                } else {
                                    foreach ($data as $k => $v) {
                                        if (stripos($k, "_id") !== false || (string) $k == "id" || substr($k, -2) == "id") {
                                            $data[$k] = trim(flayer::Crypto()->encrypt($data[$k]));
                                        }
                                    }
                                }
                            }

                            $count = null;
                        }

                        return $data;
                    } else if ($this->_set_encrypt_data) {
                        if ($mode == \PDO::FETCH_INTO || $mode == \PDO::FETCH_LAZY) {
                            $vars = null;
                            $data = [];
                            $i    = 0;

                            if ($mode == \PDO::FETCH_LAZY) {
                                while ($rows = $rs->fetch()) {
                                    $data[$i] = (array) $rows;
                                    unset($data[$i]['queryString']);

                                    foreach ($data[$i] as $k => $v) {
                                        $data[$i][$k] = trim(flayer::Crypto()->encrypt($v));
                                    }

                                    ++$i;
                                }
                            } else {
                                while ($rows = $rs->fetch()) {
                                    $vars     = (array) $rows;
                                    $data[$i] = clone $rows;

                                    foreach ($vars as $k => $v) {
                                        $data[$i]->$k = trim(flayer::Crypto()->encrypt($v));
                                    }
                                    ++$i;
                                }
                            }

                            unset($vars);
                            unset($i);
                            return $data;
                        } else {
                            if (!is_null($column_num)) {
                                $data = $rs->fetchAll($mode, $column_num);
                            } else {
                                $data = $rs->fetchAll($mode);
                            }

                            $count = $rs->rowCount();

                            if ($count > 0) {
                                if ($mode != \PDO::FETCH_INTO && is_multi_array($data)) {
                                    for ($i = 0; $i < $count; ++$i) {
                                        // $data[$i]['id'] = trim(flayer::Crypto()->encrypt($data[$i]['id']));
                                        $x = 0;
                                        foreach ($data[$i] as $k => $v) {
                                            $data[$i][$k] = trim(flayer::Crypto()->encrypt($data[$i][$k]));

                                            if ($mode == \PDO::FETCH_BOTH) {
                                                $data[$i][$x] = $data[$i][$k];
                                            }

                                            $x++;
                                        }
                                    }
                                } else {
                                    foreach ($data as $k => $v) {
                                        $data[$k] = trim(flayer::Crypto()->encrypt($data[$k]));
                                    }

                                }
                            }

                            $count = null;
                            return $data;

                        }
                    } else {
                        if ($mode == \PDO::FETCH_INTO) {
                            $vars = null;
                            $data = [];
                            $i    = 0;

                            while ($rows = $rs->fetch()) {
                                $vars     = (array) $rows;
                                $data[$i] = clone $rows;

                                foreach ($vars as $k => $v) {
                                    $data[$i]->$k = $v;
                                }
                                ++$i;
                            }

                            unset($vars);
                            unset($i);
                            return $data;
                        } else {
                            if (!is_null($column_num)) {
                                do
                                return $rs->fetchAll($mode, $column_num);while ($rs->nextRowSet());
                            } else {
                                if ($mode == \PDO::FETCH_LAZY) {
                                    // return $rs->fetchAll(\PDO::FETCH_OBJ);
                                    $data = [];
                                    $i    = 0;
                                    while ($rows = $rs->fetch()) {
                                        $data[$i] = (array) $rows;
                                        unset($data[$i]['queryString']);

                                        foreach ($data[$i] as $k => $v) {
                                            $data[$i][$k] = trim($v);
                                        }

                                        ++$i;
                                    }

                                    return $data;
                                } else {
                                    return $rs->fetchAll($mode);
                                }

                                // return $rs->fetchAll($mode);
                                // do
                                // return $rs->fetchAll($mode);while ($rs->nextRowSet());
                            }

                        }

                    }
                }

            } catch (\PDOException $e) {
                ob_start();
                print_r($e->errorInfo);
                $except = new \Exception;
                print_r($except->getTraceAsString());
                $error = ob_get_contents();
                ob_end_clean();

                $this->write_exceptional($sql, $e->getMessage(), $error);
                $this->error($rs);
                unset($error);
                unset($except);
            }
        }
    }

    /**
     * Fetch one data from DB with SQL
     *
     * @param string $sql
     * @param boolean $audit
     * @param integer $mode
     * @param integer $column_num
     * @return array
     */
    public function read_one_sql($sql, $audit = false, $mode = \PDO::FETCH_INTO, $column_num = null)
    {
        if (!empty($this->instance)) {
            $this->action_type = "Q";

            try {
                if ($this->_logger) {
                    if (stripos($sql, "SELECT LAST_INSERT_ID") !== true) {
                        $this->write_audit_log(null, "Q", null, $sql);
                        $this->_logger = false;
                    }
                }

                // if ($audit) {
                //     $this->to_audit("select", $sql);
                // }

                // preg_match_all("/^select\s+(?<columns>.*?)\s+from\s+(.*?)?((where\s+(?<where>.*?))?(order by\s+(?<order>.*?))?(limit\s+(?<limit>(.*?)))?);/i", $sql, $reg);
                // pre($reg);

                // if (strpos($sql, " limit ") !== false) {
                //     $rs = $this->instance->prepare($sql);
                // } else {
                //     $rs = $this->instance->prepare($sql . " limit 1");
                // }

                $sql = preg_replace("/(limit\s+(?<limit>(.*)))/i", "", $sql);
                $rs  = $this->instance->prepare($sql . " limit 1");

                $this->affected_Rows = $rs->execute();

                if (\PDO::FETCH_COLUMN == $mode) {
                    $rs->setFetchMode($mode, $column_num); //FETCH_ROW
                } else {
                    if ($mode == \PDO::FETCH_INTO) {
                        $rs->setFetchMode($mode, (is_object($this->fetch_mode->class) ? $this->fetch_mode->class : new $this->fetch_mode->class)); //FETCH_ROW

                    } else {
                        $rs->setFetchMode($mode); //FETCH_ROW
                    }
                }

                if ($this->_set_encrypt_id) {
                    if ($mode == \PDO::FETCH_INTO || $mode == \PDO::FETCH_LAZY) {
                        $vars = null;
                        $data = $rs->fetch();

                        if (is_object($data)) {
                            if ($mode == \PDO::FETCH_LAZY) {
                                $data = (array) $data;
                                unset($data['queryString']);

                                foreach ($data as $k => $v) {
                                    if (stripos($k, "_id") !== false || (string) $k == "id" || substr($k, -2) == "id") {
                                        $data[$k] = trim(flayer::Crypto()->encrypt($v));
                                    } else {
                                        $data[$k] = trim($v);
                                    }
                                }
                            } else {
                                $vars = (array) $data;

                                foreach ($vars as $k => $v) {
                                    if (stripos($k, "_id") !== false || (string) $k == "id" || substr($k, -2) == "id") {
                                        $data->$k = trim(flayer::Crypto()->encrypt($v));
                                    }
                                }

                                unset($vars);
                            }

                            return $data;
                        }

                        unset($vars);
                        unset($data);
                    } else {
                        $count = $rs->rowCount();
                        $data  = $rs->fetch();
                        if ($count > 0) {
                            if (is_array($data)) {
                                foreach ($data as $k => $v) {
                                    if (stripos($k, "_id") !== false || (string) $k == "id" || substr($k, -2) == "id") {
                                        $data[$k] = trim(flayer::Crypto()->encrypt($data[$k]));
                                    }
                                }
                            }
                        }

                        return $data;
                    }
                } else if ($this->_set_encrypt_data) {
                    if ($mode == \PDO::FETCH_INTO || $mode == \PDO::FETCH_LAZY) {
                        $vars = null;
                        $data = $rs->fetch();

                        if (is_object($data)) {
                            if ($mode == \PDO::FETCH_LAZY) {
                                $data = (array) $data;
                                unset($data['queryString']);

                                foreach ($data as $k => $v) {
                                    $data[$k] = trim(flayer::Crypto()->encrypt($v));
                                }
                            } else {
                                $vars = (array) $data;

                                foreach ($vars as $k => $v) {
                                    $data->$k = trim(flayer::Crypto()->encrypt($v));
                                }

                                unset($vars);
                            }
                            return $data;
                        }

                        unset($vars);
                        unset($data);
                    } else {
                        $count = $rs->rowCount();
                        $data  = $rs->fetch();
                        if ($count > 0) {
                            if (is_array($data)) {
                                foreach ($data as $k => $v) {
                                    $data[$k] = trim(flayer::Crypto()->encrypt($data[$k]));
                                }
                            }
                        }

                        return $data;
                    }
                } else {
                    if ($mode == \PDO::FETCH_INTO || $mode == \PDO::FETCH_LAZY) {
                        $data = $rs->fetch();

                        if (is_object($data)) {
                            if ($mode == \PDO::FETCH_LAZY) {
                                $data = (array) $data;
                                unset($data['queryString']);

                                foreach ($data as $k => $v) {
                                    $data[$k] = trim($v);
                                }

                                return $data;
                            } else {
                                return $data;
                            }
                        }

                        unset($data);
                    } else {
                        return $rs->fetch();
                    }
                }
            } catch (\PDOException $e) {
                //$this->instance->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                ob_start();
                print_r($e->errorInfo);
                $except = new \Exception;
                print_r($except->getTraceAsString());
                $error = ob_get_contents();
                ob_end_clean();

                $this->write_exceptional($sql, $e->getMessage(), $error);
                $this->error($rs);
                unset($error);
                unset($except);
            }
        }
    }

    /**
     * Fetch all data from DB with SQL
     *
     * @param string $sql
     * @param boolean $audit
     * @param integer $mode
     * @param integer $column_num
     * @return array
     */
    public function read_sql($sql, $audit = false, $mode = \PDO::FETCH_INTO)
    {
        if (!empty($this->instance)) {
            $this->action_type = "Q";

            try {
                // if ($audit) {
                //     $this->to_audit("select", $sql);
                // }

                $rs                  = $this->instance->prepare($sql);
                $this->affected_Rows = $rs->execute();
                $rs->setFetchMode($mode); //FETCH_ROW
                $this->error($rs);
                return $rs;
            } catch (\PDOException $e) {
                ob_start();
                print_r($e->errorInfo);
                $except = new \Exception;
                print_r($except->getTraceAsString());
                $error = ob_get_contents();
                ob_end_clean();

                $this->write_exceptional($sql, $e->getMessage(), $error);
                $this->error($rs);
                unset($error);
                unset($except);
            }
        }
    }

    /**
     * Declare right outer joins table
     *
     * Usage:
     *
     * $this->users()->right_outer_join("users_roles", $this->users()->_table, "b", "users.org_Usrgrp = b.id");
     * $this->users()->cols("id, name");
     * $this->users()->right_outer_join("name as role", "b");
     * $this->users()->right_outer_join("privileges", "b");
     * pre($this->users()->find());
     *
     * @param string $table_name Table name
     * @param string $join_to_table Join with what table's name
     * @param string $alias_name Rename the table.  e.g:  $alias_name = "a"   output: $table_name as a
     * @return FDO
     */
    public function right_outer_join($table_name, $join_to_table, $alias_name, $join_conditions)
    {
        $alias = (str($alias_name) ? $alias_name : $table_name);

        $this->_table_left_joins[$join_to_table][$alias] = " right outer join $table_name as $alias_name " . (is_array($join_conditions) && count($join_conditions) > 0 ? join(" and ", $join_conditions) : " ON ($join_conditions)");
        $this->_table_left_joins_table[$alias]           = (str($alias_name) ? $alias_name : $table_name);

        return $this;
    }

    /**
     * Declare table cols name
     *
     * Applicable for find(), findAll*(), get_all()
     *
     * Usages:
     *
     * e.g:
     *
     * $this->select("id")->select("name")->find();
     *
     * or;
     *
     * $this->select("id");
     * $this->select("name");
     * return $this->find();
     *
     * @param string|array $table_cols column name.  e.g:  id
     * @return FDO
     */
    public function select($col_name, $table_name = null)
    {
        return $this->cols($col_name, $table_name);
    }

    /**
     * Set SQL ordering sequence.  Applicable for find(), get_all(), data_all()
     *
     * @param string $sorting_cols cols name and sorting sequence.  e.g:  name asc, date desc
     * @param string $ordering_sequence ASC or DESC
     * @return FDO
     */
    public function sort_by($sorting_cols, $ordering_sequence = "")
    {
        $this->_order_by = " order by $sorting_cols $ordering_sequence";
        return $this;
    }

    /**
     * Create derived table.  Set the SQL as parent and child.  e.g output:  select * from ( MY_ORIGINAL_SQL ) as s
     *
     * Another derived function is $this->derived(), much easier to use
     *
     * Example usage :
     *
     * $app->FDO()->users()->debug()->set_encrypt_id(false)->cols("id, name")->where("id")->order_by("id", "asc")->sub()->cols("*")->fetch_mode()->limit(20)->find()
     *
     * Output:
     *
     *
     *     select
     *       \*
     *     from
     *       (
     *         select
     *           id,
     *           name
     *         from
     *           users
     *         where
     *           id is null
     *         order by
     *           id asc
     *       ) as t
     *     limit
     *       0,
     *       20
     *
     * @return FDO
     */
    public function sub()
    {
        $FDO   = clone $this;
        $debug = $FDO->_debug_print;
        $FDO->clear();

        $FDO->_set_sql_to_sub = true;
        $FDO->_debug_print    = $debug;
        $sql                  = $this->gen_select_sql()[0];
        // $FDO->set_sql("select * from ( $sql ) as s");
        $FDO->set_sql($sql);

        unset($debug);
        return $FDO;
    }

    /**
     * Get sum() from table.  Applicable for find(), get_all(), data_all()
     *
     * @param string $col column name
     * @param string $ordering_sequence ASC or DESC
     * @return FDO
     */
    public function sum_by($col, $ordering_sequence = "")
    {
        $this->_sum_by = "sum($col) as sum";
        return $this;
    }

    public function set_display_num($sql)
    {
        if ($this->version() >= 8) {
            preg_match("|select\s*(.*?)\s*from|isU", $sql, $reg);

            $first_field = "";

            if (str($reg[1])) {
                $field = preg_split('|,|', $reg[1], -1, PREG_SPLIT_NO_EMPTY);

                if (isa($field)) {
                    // foreach ($field as $k => $v) {
                    //     if (!preg_match("|\s*as\s*|", $v)) {
                    //         $first_field = trim($v);
                    //         break;
                    //     }
                    // }

                    if (preg_match("|\s*as\s*|", $field[0])) {
                        $first_field = end(preg_split("|\s*as\s*|", $field[0]));
                    } else {
                        $first_field = $field[0];
                    }
                }
            }

            // if (str($first_field)) {
            //     $sql = preg_replace("|^select|isU", "select row_number() over(order by $first_field desc) as no, ", $sql);
            // }

            // unset($first_field);
            // unset($reg);

            // $spl = preg_split("|\s*union\s*|isU", $sql, -1, PREG_SPLIT_NO_EMPTY);

            // if (isa($spl)) {
            //     $_sql = [];
            //     foreach ($spl as $v) {
            //         preg_match("|select\s*(.*?)\s*from|isU", $v, $reg);

            //         $first_field = "";

            //         if (str($reg[1])) {
            //             $field = preg_split('|,|', $reg[1], -1, PREG_SPLIT_NO_EMPTY);

            //             if (isa($field)) {
            //                 foreach ($field as $field_v) {
            //                     if (!preg_match("|\s*as\s*|", $field_v)) {
            //                         $first_field = trim($field_v);
            //                         break;
            //                     }
            //                 }
            //             }
            //         }

            //         if (str($first_field)) {
            //             $_sql[] = trim(preg_replace("|^select|isU", "select row_number() over(order by $first_field desc) as no,", $v));
            //         } else {
            //             $_sql[] = trim($v);
            //         }

            //         unset($first_field);
            //         unset($reg);
            //     }

            //     $sql = join(" union ", $_sql);

            // }

            if (str($first_field)) {
                if (stripos($first_field, ".") !== false) {
                    list(, $first_field) = explode(".", $first_field);
                }

                $num = 0;
                if (is_value($this->_limit)) {
                    if (stripos($this->_limit, ",") !== false) {
                        list($num) = explode(",", $this->_limit);
                        $num       = (int) trim(str_ireplace("limit", "", $num));
                    }
                }

                // $sql = "select row_number() over(order by $first_field desc) as no, t.* from ( $sql ) as t";

                // list(, $first_field) = explode(".", $first_field);

                // $sql = "select ROW_NUMBER() OVER w AS 'no', original_sql.* from ( $sql ) original_sql WINDOW w AS (ORDER BY $first_field)";

                $sql = "select ( $num + ROW_NUMBER() OVER w ) AS 'no', original_sql.* from ( $sql ) original_sql";

                if (is_value($this->_order_by)) {
                    $sql .= " WINDOW w AS (" . $this->_order_by . ")";
                } else {
                    $sql .= " WINDOW w AS (ORDER BY $first_field)";
                }

                $this->_order_by = "";
                $this->_limit    = "";
            }

            unset($first_field);
            unset($reg);
        } else {
            $sql = "select ( @rownum := (@rownum + 1) ) as no, original_sql.* from ( $sql ) original_sql";

            $this->execute("SET @rownum := 0;");
        }

        return $sql;
    }

    public function set_sql($sql)
    {
        $this->_sql = $sql;
    }

    /**
     * Get generated complete SQL
     *
     * @return string
     */
    public function sql()
    {
        $this->_sql_only = true;
        return $this->find();
    }

    /**
     * Get generated SQL without SELECT ... FROM
     *
     * @return string
     */
    public function sql_without_select()
    {
        $this->_sql_without_select = true;
        return $this->find();
    }

    /**
     * Generate date with $_POST['sdate'] and $_POST['edate']
     *
     * @param string $table_field_name Table field's name  e.g: birth_date
     * @param array $data $_POST or $this->_post
     * @return string
     */
    public function sql_search_date($table_field_name, &$data)
    {
        if (isset($data['sdate']) && str($data['sdate'])) {
            $data['sdate'] = auto_date($data['sdate'], "Y-m-d");
        }

        if (isset($data['edate']) && str($data['edate'])) {
            $data['edate'] = auto_date($data['edate'], "Y-m-d");
        }

        $validate = $this->validate("date", [
            "sdate" => "Start date",
            "edate" => "End date",
        ]);

        if ($validate[0] != 1) {
            return $validate;
        }

        unset($validate);

        $sql = "";

        if (is_null($data)) {
            $data = $this->_post;
        }

        if (isset($data['sdate']) && isset($data['edate']) && str($data['sdate']) && str($data['edate'])) {
            $sql .= " and (date($table_field_name) between '" . $data['sdate'] . "' and '" . $data['edate'] . "')";
        } else {
            if (isset($data['sdate']) && str($data['sdate'])) {
                $sql .= " and date($table_field_name) >= '" . $data['sdate'] . "'";
            }

            if (isset($data['edate']) && str($data['edate'])) {
                $sql .= " and date($table_field_name) <= '" . $data['edate'] . "'";
            }
        }

        return $sql;
    }

    /**
     * Generate time with $_POST['stime'] and $_POST['etime']
     *
     * @param string $table_field_name Table field's name  e.g: login_time
     * @param array $data $_POST or $this->_post
     * @return string
     */
    public function sql_search_time($table_field_name, &$data)
    {
        $validate = $this->validate("time", [
            "stime" => "Start time",
            "etime" => "End time",
        ]);

        if ($validate[0] != 1) {
            return $validate;
        }

        unset($validate);

        $sql = "";

        if (is_null($data)) {
            $data = $this->_post;
        }

        if (isset($data['stime']) && isset($data['etime']) && str($data['stime']) && str($data['etime'])) {
            $sql .= " and ( time($table_field_name) between '" . $data['stime'] . "' and '" . $data['etime'] . "' )";
        } else {
            if (isset($data['stime']) && str($data['stime'])) {
                $sql .= " and time($table_field_name) >= '" . $data['stime'] . "'";
            }

            if (isset($data['etime']) && str($data['stime'])) {
                $sql .= " and time($table_field_name) <= '" . $data['etime'] . "'";
            }
        }

        return $sql;
    }

    /**
     * Generate "from date" and "to date" with $_POST['sdate'] and $_POST['edate']
     *
     * @param string $table_field_name_from Table field's name  e.g: log_start_date
     * @param string $table_field_name_to Table field's name  e.g: log_end_date
     * @param array $data $_POST or $this->_post
     * @return string
     */
    public function sql_search_from_to_date($table_field_name_from, $table_field_name_to, &$data)
    {
        if (isset($data['sdate']) && str($data['sdate'])) {
            $data['sdate'] = auto_date($data['sdate'], "Y-m-d");
        }

        if (isset($data['edate']) && str($data['edate'])) {
            $data['edate'] = auto_date($data['edate'], "Y-m-d");
        }

        $validate = $this->validate("date", [
            "sdate" => "Start date",
            "edate" => "End date",
        ]);

        if ($validate[0] != 1) {
            return $validate;
        }

        unset($validate);

        $sql = "";

        if (is_null($data)) {
            $data = $this->_post;
        }

        if (isset($data['sdate']) && isset($data['edate']) && str($data['sdate']) && str($data['edate'])) {
            $sql .= " and (date($table_field_name_from) >= '" . $data['sdate'] . "' and date($table_field_name_to) <= '" . $data['edate'] . "')";
        } else {
            if (isset($data['sdate']) && str($data['sdate'])) {
                $sql .= " and date($table_field_name_from) >= '" . $data['sdate'] . "'";
            }

            if (isset($data['edate']) && str($data['edate'])) {
                $sql .= " and date($table_field_name_to) <= '" . $data['edate'] . "'";
            }
        }

        return $sql;
    }

    /**
     * Generate "from time" and "to time" with $_POST['sdate'] and $_POST['edate']
     *
     * @param string $table_field_name_from Table field's name  e.g: log_start_time
     * @param string $table_field_name_to Table field's name  e.g: log_end_time
     * @param array $data $_POST or $this->_post
     * @return string
     */
    public function sql_search_from_to_time($table_field_name_from, $table_field_name_to, &$data)
    {
        $validate = $this->validate("time", [
            "stime" => "Start time",
            "etime" => "End time",
        ]);

        if ($validate[0] != 1) {
            return $validate;
        }

        unset($validate);

        $sql = "";

        if (is_null($data)) {
            $data = $this->_post;
        }

        if (isset($data['stime']) && isset($data['etime']) && str($data['stime']) && str($data['etime'])) {
            $sql .= " and ( time($table_field_name_from) >= '" . $data['stime'] . "' and time($table_field_name_to) <= '" . $data['etime'] . "' )";
        } else {
            if (isset($data['stime']) && str($data['stime'])) {
                $sql .= " and time($table_field_name_from) >= '" . $data['stime'] . "'";
            }

            if (isset($data['etime']) && str($data['stime'])) {
                $sql .= " and time($table_field_name_to) <= '" . $data['etime'] . "'";
            }
        }

        return $sql;
    }

    /**
     * Generate create date & time with $_POST['sdate'] and $_POST['edate'], $_POST['stime'] and $_POST['etime']
     *
     * @param array $data $_POST or $this->_post
     * @param array $table_name_or_alias_name Table alias name.  e.g:  $table_name_or_alias_name = a  ( select * from table_name as a )
     * @return string
     */
    public function sql_search_create_date_time(&$data, $table_name_or_alias_name = null)
    {
        if (isset($data['sdate']) && str($data['sdate'])) {
            $data['sdate'] = auto_date($data['sdate'], "Y-m-d");
        }

        if (isset($data['edate']) && str($data['edate'])) {
            $data['edate'] = auto_date($data['edate'], "Y-m-d");
        }

        $validate = $this->validate("date", [
            "sdate" => "Start date",
            "edate" => "End date",
        ]);

        if ($validate[0] != 1) {
            return $validate;
        }

        unset($validate);

        $validate = $this->validate("time", [
            "stime" => "Start time",
            "etime" => "End time",
        ]);

        if ($validate[0] != 1) {
            return $validate;
        }

        unset($validate);

        // $sql = "crby='" . $this->_user . "'";
        // $sql = "id is not null";

        $sql    = "";
        $prefix = "";

        if (!is_null($table_name_or_alias_name)) {
            $prefix = $table_name_or_alias_name . ".";
        }

        if (is_null($data)) {
            $data = $this->_post;
        }

        if (isset($data['sdate']) && isset($data['edate']) && str($data['sdate']) && str($data['edate'])) {
            $sql .= " and date(${prefix}crdt) between '" . $data['sdate'] . "' and '" . $data['edate'] . "'";
        } else {
            if (isset($data['sdate']) && str($data['sdate'])) {
                $sql .= " and date(${prefix}crdt) >= '" . $data['sdate'] . "'";
            }

            if (isset($data['edate']) && str($data['edate'])) {
                $sql .= " and date(${prefix}crdt) <= '" . $data['edate'] . "'";
            }
        }

        if (isset($data['stime']) && isset($data['etime']) && str($data['stime']) && str($data['etime'])) {
            $sql .= " and time(${prefix}crdt) between '" . $data['stime'] . "' and '" . $data['etime'] . "'";
        } else {
            if (isset($data['stime']) && str($data['stime'])) {
                $sql .= " and time(${prefix}crdt) >= '" . $data['stime'] . "'";
            }

            if (isset($data['etime']) && str($data['stime'])) {
                $sql .= " and time(${prefix}crdt) <= '" . $data['etime'] . "'";
            }
        }

        return $sql;
    }

    /**
     * Generate custom start / end date / time HTTP POST field's name's SQL
     *
     * @param string $data
     *
     * Pre-defined parameter:
     *
     * sdate
     * edate
     * stime
     * etime
     *
     * e.g : $data = ["sdate" => "ANY_FIELD_NAME", "edate" => "ANY_FIELD_NAME_2"];
     *
     * @param string $table_name_or_alias_name
     * @return string
     */
    public function sql_search_custom_date_time($data, $table_name_or_alias_name = null)
    {
        // $sql = "crby='" . $this->_user . "'";
        // $sql = "id is not null";

        $sql    = "";
        $prefix = "";

        if (!is_null($table_name_or_alias_name)) {
            $prefix = $table_name_or_alias_name . ".";
        }

        if (isset($data['sdate']) || isset($data['edate'])) {
            if (isset($data['sdate']) && str($data['sdate'])) {
                $data['sdate'] = auto_date($this->_request[$data['sdate']], "Y-m-d");
            }

            if (isset($data['edate']) && str($data['edate'])) {
                $data['edate'] = auto_date($this->_request[$data['edate']], "Y-m-d");
            }

            if (isset($data['sdate']) && str($data['sdate'])) {
                $validate = $this->validate("date", [
                    $data['sdate'] => $data['sdate'],
                ]);

                if ($validate[0] != 1) {
                    return $validate;
                }
            }

            if (isset($data['edate']) && str($data['edate'])) {
                $validate = $this->validate("date", [
                    $data['edate'] => $data['edate'],
                ]);

                if ($validate[0] != 1) {
                    return $validate;
                }
            }

            unset($validate);

            if (isset($data['sdate']) && isset($data['edate']) && str($data['sdate']) && str($data['edate'])) {
                $sql .= " and date(${prefix}crdt) between '" . $data['sdate'] . "' and '" . $data['edate'] . "'";
            } else {
                if (isset($data['sdate']) && str($data['sdate'])) {
                    $sql .= " and date(${prefix}crdt) >= '" . $data['sdate'] . "'";
                }

                if (isset($data['edate']) && str($data['edate'])) {
                    $sql .= " and date(${prefix}crdt) <= '" . $data['edate'] . "'";
                }
            }
        }

        if (isset($data['stime']) || isset($data['etime'])) {
            if (isset($data['stime']) && str($data['stime'])) {
                $data['stime'] = $this->_request[$data['stime']];
            }

            if (isset($data['etime']) && str($data['etime'])) {
                $data['etime'] = $this->_request[$data['etime']];
            }

            $validate = $this->validate("time", [
                $data['stime'] => $data['stime'],
                $data['etime'] => $data['etime'],
            ]);

            if ($validate[0] != 1) {
                return $validate;
            }

            unset($validate);

            // if (isset($this->_request[$data['stime']]) && isset($this->_request[$data['etime']]) && str($this->_request[$data['stime']]) && str($this->_request[$data['etime']])) {
            //     $sql .= " and time(${prefix}crdt) between '" . $this->_request[$data['stime']] . "' and '" . $this->_request[$data['etime']] . "'";
            // } else {
            //     if (isset($this->_request[$data['stime']]) && str($this->_request[$data['stime']])) {
            //         $sql .= " and time(${prefix}crdt) >= '" . $this->_request[$data['stime']] . "'";
            //     }

            //     if (isset($this->_request[$data['etime']]) && str($this->_request[$data['stime']])) {
            //         $sql .= " and time(${prefix}crdt) <= '" . $this->_request[$data['etime']] . "'";
            //     }
            // }

            if (isset($data['stime']) && isset($data['etime']) && str($data['stime']) && str($data['etime'])) {
                $sql .= " and time(${prefix}crdt) between '" . $data['stime'] . "' and '" . $data['etime'] . "'";
            } else {
                if (isset($data['stime']) && str($data['stime'])) {
                    $sql .= " and time(${prefix}crdt) >= '" . $data['stime'] . "'";
                }

                if (isset($data['etime']) && str($data['stime'])) {
                    $sql .= " and time(${prefix}crdt) <= '" . $data['etime'] . "'";
                }
            }
        }

        return $sql;
    }

    public function sql_search_create_month(&$s_mth, &$e_mth, $table_name_or_alias_name = null)
    {
        $sql    = "";
        $prefix = "";

        if (!is_null($table_name_or_alias_name)) {
            $prefix = $table_name_or_alias_name . ".";
        }

        if ($s_mth > 0 && $e_mth > 0) {
            $sql .= " and month(${prefix}crdt) between '" . $s_mth . "' and '" . $e_mth . "'";
        } else {
            if ($s_mth > 0) {
                $sql .= " and month(${prefix}crdt) >= '" . $s_mth . "'";
            }

            if ($e_mth > 0) {
                $sql .= " and month(${prefix}crdt) <= '" . $e_mth . "'";
            }
        }

        return $sql;
    }

    public function sql_search_create_year(&$s_yr, &$e_yr, $table_name_or_alias_name = null)
    {

        $sql    = "";
        $prefix = "";

        if (!is_null($table_name_or_alias_name)) {
            $prefix = $table_name_or_alias_name . ".";
        }

        if ($s_yr > 0 && $e_yr > 0) {
            $sql .= " and date(${prefix}crdt) between '" . $s_yr . "' and '" . $e_yr . "'";
        } else {
            if ($s_yr > 0) {
                $sql .= " and date(${prefix}crdt) >= '" . $s_yr . "'";
            }

            if ($e_yr > 0) {
                $sql .= " and date(${prefix}crdt) <= '" . $e_yr . "'";
            }
        }

        return $sql;
    }

    public function sql_search_date_range(&$start, &$end, $table_name_or_alias_name = null)
    {

        $sql    = "";
        $prefix = "";

        if (!is_null($table_name_or_alias_name)) {
            $prefix = $table_name_or_alias_name . ".";
        }

        if ($start > 0 && $end > 0) {
            $sql .= " and year(${prefix}crdt) between '" . $start . "' and '" . $end . "'";
        } else {
            if ($start > 0) {
                $sql .= " and year(${prefix}crdt) >= '" . $start . "'";
            }

            if ($end > 0) {
                $sql .= " and year(${prefix}crdt) <= '" . $end . "'";
            }
        }

        return $sql;
    }

    /**
     * Set union query
     *
     * Applicable for find()
     *
     * Usages :
     *
     * $a = $this->union("logs_offence");
     * $a->cols("id, action");
     * $a->where("id is not null");
     *
     * $this->union("logs_st_feedback")->cols("id, action")->where("id is not null and ip is not null");
     *
     * $a->where("ip is null");
     *
     * $this->debug(false);
     * pre($this->order_by("action asc")->find("id, action"));
     *
     * unset($a);
     *
     *
     * @param string $table_name
     * @return array
     */
    public function union($table_name = null)
    {
        if (str($table_name)) {
            $name = $table_name;
        } else {
            $name = $this->_table;
        }

        if (!isset($this->_union_tables[$name])) {
            $instance = clone $this;
            $instance->instance($this->instance);
            $instance->action_description      = null;
            $instance->action_type             = null;
            $instance->_table_alias            = "";
            $instance->_table_temp_alias       = "";
            $instance->_table_cols             = "";
            $instance->_table_cols_nums        = 0;
            $instance->_table_join             = null;
            $instance->_table_joins            = null;
            $instance->_table_joins_table      = null;
            $instance->_table_left_joins       = null;
            $instance->_union                  = null;
            $instance->_union_tables           = null;
            $instance->_table_left_joins_table = null;
            $instance->_vars                   = null;
            $instance->_raws                   = null;
            $instance->_where                  = "";
            $instance->_limit                  = "";
            $instance->_count_by               = "";
            $instance->_count_group            = false;
            $instance->_max_by                 = "";
            $instance->_group_by               = "";
            $instance->_order_by               = "";
            $instance->debug($this->_debug_print);
            $instance->off_print_format($this->_off_print_format);
            $instance->set_table($name);
            $instance->soft_update($this->_soft_update);

            $this->_union_tables[$name] = $instance;
            unset($instance);
        }

        $this->_union = true;
        return $this->_union_tables[$name];
    }

    /**
     * Generate SQL "where" statement
     *
     * e.g: $this->where("id = 123");  // output select * from example where id = 123
     *
     * or output: update tests set name = 'Test' where id = 123;
     *
     *
     * e.g: $this->where("id", 123);  // output: select * from example where id = 123
     *
     * e.g: $this->where("id"); // output: select * from example where id is null
     *
     * e.g: $this->where("id", ""); // output: select * from example where id is null
     *
     * e.g: $this->where(["id" => 123, "status" => "A"]);  // output select * from example where id = 123 and status = 'A'
     *
     * e.g: $this->where(["id", "f_id"], 123);  // output select * from example where id = 123 and f_id = 123
     *
     * e.g: $this->where("id", [123, 456]); // output select * from example where id = 123 and id = 456
     *
     * e.g: $this->where("id = 123")->get("table", "table_columns");
     *
     * e.g: $this->where("id", array(1, 2, 3))->find();  //SQL output = select * from xxx where id in (1, 2, 3);  or  select * from xxx where id in ('a', 'b', 'c');
     *
     * @param string|array $sql_where SQL where statement.  e.g: id = 123 and userid='admin'
     * @param string|array value    e.g: id in ($array_value) = id in (1, 2, 3)
     */
    public function where($sql_where, $value = null)
    {
        // $this->_where = $sql_where;
        $str = false;

        if (str($this->_where)) {
            $this->_where .= " ";
            $str = true;
        }

        if (is_array($sql_where) && count($sql_where) > 0) {
            if (is_assoc_array($sql_where) && is_null($value)) {
                foreach ($sql_where as $k => $v) {
                    if (!is_array($v) && str($v)) {
                        $v = trim($v);

                        if (is_int($k)) {
                            $v = trim($v);
                        } else {
                            $sql_where[$k] = "$k = " . trim(preg_replace("/^(and)/si", "", (is_numeric($v) ? $v : "'$v'")));
                        }
                    }
                }
            } else {
                foreach ($sql_where as $k => $v) {
                    if (!is_array($value) && str($value)) {
                        if (!str($v)) {
                            unset($sql_where[$k]);
                        } else {
                            $sql_where[$k] = "$v = " . (is_numeric($value) ? $value : "'$value'");
                        }
                    } else {
                        if (!str($v)) {
                            unset($sql_where[$k]);
                        } else {
                            $sql_where[$k] = trim(preg_replace("/^(and)/si", "", trim($v)));
                        }
                    }
                }
            }

            $this->_where .= ($str ? " and " : "") . join(" and ", $sql_where);
            unset($sql_where);
        } else if (str($sql_where)) {
            if (is_array($sql_where)) {
                $sql_where = preg_replace("/^and/si", "", $sql_where);
                if (is_array($sql_where)) {
                    $sql_where = join(" ", $sql_where);
                }
            } else {
                $sql_where = preg_replace("/^and/si", "", trim($sql_where));
            }

            $sql_where = trim($sql_where);

            if (is_array($value) && count($value) > 0) {
                $this->_where .= ($str ? " and " : "") . "$sql_where in (" . join(", ", array_map(
                    function ($id) {
                        if (!is_numeric($id)) {
                            if (substr($id, 0, 1) != "'" && substr($id, -1, 1) != "'") {
                                return "'$id'";
                            } else {
                                return $id;
                            }
                        } else {
                            return $id;
                        }
                    },
                    $value
                )) . ")";
            } else {
                if (!is_array($value) && str($value)) {
                    if ($value != "now()" && $value != "current_date" && $value != "current_timestamp" && $value != "year(curdate())" && $value != "month(curdate())" && stripos($value, "(case when") === false && (substr($value, 0, 1) != "(" && substr($value, 1, -1) != ")")) {
                        $this->_where .= ($str ? " and " : "") . "$sql_where = " . (is_numeric($value) ? $value : "'$value'");
                    } else {
                        $this->_where .= ($str ? " and " : "") . "$sql_where = $value";
                    }
                } else {
                    if (!str($value)) {
                        if (str($sql_where)) {
                            $this->_where .= ($str ? " and " : "") . $sql_where;

                            // if(preg_match("/^\(.*\)$/isU", $sql_where)){
                            if (!preg_match("/^(and|or)\s/isU", $sql_where) && (substr($sql_where, 0, 1) != "(" && substr($sql_where, -1) != ")")) {
                                if (!preg_match("/(lower|upper|like|is|is not|in|between|not|=|>|<)[\S|\W|\d]/isU", $sql_where)) {
                                    $this->_where .= " is null";
                                }
                            }
                        }
                    } else {
                        $this->_where .= ($str ? " and " : "") . $sql_where;
                    }
                }
            }

            unset($str);
        }

        return $this;
    }

    /**
     * In between SQL query
     *
     * e.g:  $this->where_between("id", 1, 20) // output : select * from abc where id between 1 and 20
     * e.g:  $this->where_between("id", 1, 20, true) // output : select * from abc where status = 'A' and (id between 1 and 20) and date = null
     *
     * @param string $table_col_name Columns name.  e.g:  id
     * @param string $first_value Any value to compare
     * @param string $last_value Any value to compare
     * @param boolean $separate_query Default = false.  If set to true, will add " ( ) " in front and at the end of BETWEEN.  e.g output:  (id between 1 and 20)
     * @return db_helper
     */
    public function where_between($table_col_name, $first_value, $last_value, $separate_query = false)
    {
        $this->where(($separate_query ? "( " : "") . "$table_col_name between " . (is_numeric($first_value) ? $first_value : "'$first_value'") . " and " . (is_numeric($last_value) ? $last_value : "'$last_value'") . ($separate_query ? " )" : ""));

        return $this;
    }

    /**
     * Fetch data with running number.  Applicable to find() and findAllBy()
     *
     * e.g:  select name from aaa;
     *
     * num name
     * 1.  ABC
     * 2.  CDE
     *
     * @param boolean $bool
     * @return db_helper
     */
    public function with_display_num($bool = true)
    {
        $this->_sql_display_num = $bool;
        return $this;
    }

    #################################################### Private ###############################################

    /**
     * Generate SQL by selected parameter options
     *
     * @param string|array $find_by_column_name Table name or table columns name
     * @param mixed [$data] Column's value.  (applicable for UPDATE statement)
     * @param string|array $select_cols_name Table columns name (if $find_by_column_name = TABLE NAME)
     * @return array
     */
    private function gen_select_sql($find_by_column_name = null, $data = null, $select_cols_name = "*")
    {
        if ($this->_set_encrypt_id) {
            $this->set_encrypt_id();
        }

        $sql                   = "";
        $mode                  = $this->fetch_mode->mode;
        $table                 = $this->_table . (str($this->_table_alias) ? " as " . $this->_table_alias : "");
        $table_                = array();
        $sql                   = "";
        $sql_                  = array();
        $table_[$this->_table] = $table;

        list($select_cols_name, $mode) = $this->_gen_select_cols($find_by_column_name, $select_cols_name);

        if (is_array($this->_table_join)) {
            foreach ($this->_table_join as $k => $v) {
                $table_[$k] = $v[0];
                $sql_[$k]   = $v[2];
            }
        }

        if (is_array($this->_table_joins)) {
            foreach ($this->_table_joins as $k => $v) {
                if (isset($table_[$k])) {
                    $table_[$k] .= join(" ", $v);
                }
            }
        }

        if (is_array($this->_table_left_joins)) {
            foreach ($this->_table_left_joins as $k => $v) {
                if (isset($table_[$k])) {
                    $table_[$k] .= join(" ", $v);
                }
            }
        }

        $table = join(", ", $table_);
        $sql   = join(" and ", $sql_);

        if (str($this->_where)) {
            $sql .= (str($sql) ? " and " : "") . $this->_where;
        }

        if (isset($this->_count_by) && str($this->_count_by)) {
            $this->_limit = "";

            if ($this->_sql_without_select) {
                $this->_sql = "from " . $table . (str($sql) ? " where $sql" : "") . $this->_group_by . $this->_order_by;
            } else {
                if ($this->_set_sql_to_sub) {
                    $this->_sql = "select " . $this->_count_by . " as count from ( " . $this->_sql . " ) ";
                } else {
                    $this->_sql = "select " . $this->_count_by . " as count from " . $table;
                }

                $this->_sql .= (str($sql) ? " where $sql" : "") . $this->_group_by . $this->_order_by;
            }

        } else if (isset($this->_sum_by) && str($this->_sum_by)) {
            if ($this->_sql_without_select) {
                $this->_sql = "from " . $table . (str($sql) ? " where $sql" : "") . $this->_group_by . $this->_order_by;
            } else {
                if ($this->_set_sql_to_sub) {
                    $this->_sql = "select " . $this->_sum_by . " from ( " . $this->_sql . " ) ";
                } else {
                    $this->_sql = "select " . $this->_sum_by . " from " . $table;
                }

                $this->_sql .= (str($sql) ? " where $sql" : "") . $this->_group_by . $this->_order_by;
            }
        } else {
            if ($this->_sql_without_select) {
                $this->_sql = "from " . $table . (str($sql) ? " where $sql" : "") . $this->_group_by . $this->_order_by . $this->_limit;
            } else {
                if (!str($select_cols_name)) {
                    $select_cols_name = "*";
                }

                if ($this->_set_sql_to_sub) {
                    if (str($this->_sql)) {
                        $this->_sql = "select $select_cols_name from ( " . $this->_sql . " ) as t ";
                    } else {
                        $this->_sql = "select $select_cols_name from " . $table;
                    }
                } else {
                    $this->_sql = "select $select_cols_name from " . $table;
                }

                if ($this->derived) {
                    $this->_sql = "select $select_cols_name from " . (is_value($this->derived_sql) ? "( " . $this->derived_sql . " ) as $table" : "") . (is_value($sql) ? " where $sql" : "") . $this->_group_by . $this->_order_by . $this->_limit;

                    if (str($this->_table_alias)) {
                        $this->_table_alias      = "";
                        $this->_table_alias_temp = null;
                    }
                } else {
                    $this->_sql .= (str($sql) ? " where $sql" : "") . $this->_group_by . $this->_order_by . $this->_limit;
                    $this->_sql_without_limit = "select '' from " . $table . (str($sql) ? " where $sql" : "") . $this->_group_by;
                }
            }
        }

        return [$this->_sql, $mode];
    }

    /**
     * Generate SELECT statement table columns by parameters options
     *
     * @param mixed $find_by_column_name
     * @param mixed $select_cols_name
     * @return array
     */
    private function _gen_select_cols($find_by_column_name = null, $select_cols_name = "*")
    {
        $sql          = "";
        $table_prefix = "";
        $mode         = \PDO::FETCH_INTO;
        $cols         = [];
        $_            = [];

        if (is_array($this->_table_join) || is_array($this->_table_joins) || is_array($this->_table_left_joins_table)) {
            $table_prefix = (str($this->_table_alias) ? $this->_table_alias : $this->_table) . ".";
        }

        if (!is_null($find_by_column_name)) {
            if (stripos($find_by_column_name, ",") !== false) {
                $_ = explode(",", $find_by_column_name);

                if ($select_cols_name == "*") {
                    if (str($table_prefix)) {
                        foreach ($_ as $k => $v) {
                            $cols[] = $table_prefix . trim($v);
                        }

                        $select_cols_name = join(", ", $cols);
                    } else {
                        $select_cols_name = join(", ", $_);
                    }
                } else {
                    $select_cols_name = $find_by_column_name;
                }

                unset($_);
                unset($__);
                unset($cols);
            } else if (stripos($find_by_column_name, " or ") !== false) {
                $_ = explode("or", $find_by_column_name);

                if ($select_cols_name == "*") {
                    if (str($table_prefix)) {
                        foreach ($_ as $k => $v) {
                            $cols[] = $table_prefix . trim($v);
                        }

                        $select_cols_name = join(", ", $cols);
                    } else {
                        $select_cols_name = join(", ", $_);
                    }
                }
                unset($_);
                unset($__);
                unset($cols);
            } else if (stripos($find_by_column_name, "and") !== false) {
                $reg = preg_split("/(and|,)/si", $find_by_column_name);

                if ($select_cols_name == "*") {
                    if (str($table_prefix)) {
                        foreach ($_ as $k => $v) {
                            $cols[] = $table_prefix . trim($v);
                        }

                        $select_cols_name = join(", ", $cols);
                    } else {
                        $select_cols_name = join(", ", $_);
                    }
                }

                unset($_);
                unset($__);
                unset($reg);
                unset($cols);
            } else {
                if (str($find_by_column_name)) {
                    $select_cols_name = $find_by_column_name;
                    $mode             = \PDO::FETCH_COLUMN;
                }
            }
        }

        if (str($this->_table_cols)) {
            $select_cols_name = (str($select_cols_name) && $select_cols_name != "*" ? $select_cols_name . ", " : "") . $this->_table_cols;
        }

        unset($table);
        unset($sql);

        if (isset($this->_count_by) && str($this->_count_by)) {
            $select_cols_name = " " . $this->_count_by . " as count ";
        }

        if (isset($this->_max_by) && str($this->_max_by)) {
            $select_cols_name = " " . $this->_max_by . " as max ";
        }

        $this->_table_cols_nums = count(explode(",", $select_cols_name));

        return [$select_cols_name, $mode];
    }

    private function _receive_raws($data)
    {
        if ($this->_enable_logger) {
            $this->_raws = &$data;
        }

        if (is_array($data) && !isset($data[0])) {
            if (count($data) == 1) {
                return array_values($data)[0];
            }
        }

        if (!$this->_set_encrypt_data) {
            $this->set_encrypt_data(false);
        }

        if (!$this->_set_encrypt_id) {
            $this->set_encrypt_id(false);
        }

        return $data;
    }
}
