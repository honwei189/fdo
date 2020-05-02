<?php
/*
 * @creator           : Gordon Lim <honwei189@gmail.com>
 * @created           : 12/05/2019 17:43:32
 * @last modified     : 02/05/2020 16:03:05
 * @last modified by  : Gordon Lim <honwei189@gmail.com>
 */

namespace honwei189\fdo;

/**
 *
 * DB operation function
 *
 *
 * @package     fdo
 * @subpackage
 * @author      Gordon Lim <honwei189@gmail.com>
 * @link        https://github.com/honwei189/fdo/
 * @link        https://appsw.dev
 * @link        https://justtest.app
 * @version     "1.0.0" 
 * @since       "1.0.0" 
 */
trait operate
{
    private $_vars = [];

    /**
     * @access private
     * @internal
     */
    public function __construct()
    {
    }

    /**
     * Insert data into DB
     *
     * @return array
     */
    public function add()
    {
        return $this->store();
    }

    /**
     * Insert data into DB
     *
     * @return array
     */
    public function create()
    {
        return $this->store();
    }

    /**
     * Delete data from DB
     * @param string $sql_where
     * @return boolean
     */
    public function delete($sql_where = null)
    {
        if ($sql_where === null) {
            if ($this->_where === null) {
                $sql_where = "crby = '" . $this->_user . "' and id = " . (int) $this->_id;
            } else {
                $sql_where = $this->_where;
            }
        }

        if ($this->_enable_logger) {
            if (!$this->_debug_print) {
                $this->set_try_catch();
                $this->_raws = $this->where($sql_where)->find();

                if (!$this->_raws) {
                    return false;
                }
            }
        }

        $stat = false;

        $sql = "delete from $this->_table where " . $sql_where;

        if ($this->_debug_print) {
            $this->print_sql_format($sql);
            $stat = 1;
            if (!$this->_soft_update) {
                exit;
            }
        } else {
            if ($this->_soft_update) {
                if (!$this->_trx) {
                    $this->begin();
                }
            }

            $this->delete_sql($sql);
            $stat = !$this->is_error;

            if (!$this->_soft_update) {
                if (isset($this->_raws) && is_array($this->_raws) && count($this->_raws) > 0) {
                    foreach ($this->_raws as $k => $v) {
                        $this->write_audit_log((isset($v['id']) ? (int) $v['id'] : (int) $this->_id), "D", $v, $sql_where);
                    }
                }
            }
        }

        $this->_raws                   = null;
        $this->_where                  = "";
        $this->_count_by               = "";
        $this->_count_group            = false;
        $this->_group_by               = "";
        $this->_order_by               = "";
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

        if (is_value($this->_table_alias_temp)) {
            $this->_table_alias      = $this->_table_alias_temp;
            $this->_table_alias_temp = null;
        }

        return $stat;
    }

    /**
     * Delete data from DB with SQL
     *
     * @param string $sql
     * @return int Number of affected rows
     */
    public function delete_sql($sql)
    {
        if (!empty($this->instance)) {
            try {
                $rs                  = $this->instance->prepare($sql);
                $this->affected_Rows = $rs->execute();
                $this->error($rs);

                unset($rs);
                return $this->affected_Rows;
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
     * Update data
     *
     * @return array
     */
    public function edit($sql_where = null)
    {
        return $this->update($sql_where);
    }

    public function execute($sql)
    {
        if (!empty($this->instance)) {
            try {
                $rs                  = $this->instance->prepare($sql);
                $this->affected_Rows = $rs->execute();
                $rs->setFetchMode(\PDO::FETCH_NUM);
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
     * Get last insert ID
     *
     * @return integer
     */
    public function get_insert_id()
    {
        return $this->_id;
    }

    /**
     * Remove data from DB
     *
     * @return array
     */
    public function remove($sql_where = null)
    {
        return $this->delete($sql_where);
    }

    /**
     * Insert or update data into DB
     *
     * If data exist, it is UPDATE.  Otherwise it is INSERT
     *
     * @return boolean
     */
    public function save($sql_where = null)
    {
        if (is_array($this->_vars) && count($this->_vars) > 0) {
            $new_data = false;

            if (!is_value($sql_where)) {
                if ($this->_where === null) {
                    $sql_where = "crby = '" . $this->_user . "' and id = " . (int) $this->_id;
                } else {
                    $sql_where = $this->_where;
                }
            }else{
                if ((int)$sql_where > 0){
                    $this->by_id($sql_where);
                    $sql_where = $this->_where;
                }
            }

            if (!is_value($sql_where)) {
                $new_data = true;
            } else {
                $_debug_print       = $this->_debug_print;
                $this->_debug_print = false;
                $this->_raws        = $this->findBy(null, $sql_where, join(", ", array_keys($this->_vars)));
                $this->_debug_print = $_debug_print;
                unset($_debug_print);

                if (!$this->_raws) {
                    $new_data = true;
                }
            }

            if ($new_data) {
                if (isset($this->_vars['status']) && $this->_vars['status'] == "I") {
                    unset($this->{"deldate"});
                    unset($this->{"deletedate"});
                    unset($this->{"dldt"});
                    unset($this->{"ddt"});
                    unset($this->{"dby"});
                    unset($this->{"deleteby"});
                    unset($this->{"dby"});
                    unset($this->{"deleted_by"});
                    unset($this->{"deleted_at"});
                } else {
                    unset($this->{"lupdate"});
                    unset($this->{"ldt"});
                    unset($this->{"lupdt"});
                    unset($this->{"lupby"});
                    unset($this->{"lupdateby"});
                    unset($this->{"updatedate"});
                    unset($this->{"updated_at"});
                    unset($this->{"updated_by"});
                }

                $keys = array_keys($this->_vars);
                $raws = $this->_vars;

                foreach ($this->_vars as $k => $v) {
                    if (is_value($v)) {
                        if ($v != "now()" && $v != "current_date" && $v != "current_timestamp" && $v != "year(curdate())" && $v != "month(curdate())" && stripos($v, "(case when") === false && (substr($v, 0, 1) != "(" && substr($v, 1, -1) != ")")) {

                            if (substr($v, 0, 1) == "(" && substr($v, 1, -1) == ")" && strpos($v, "+") !== false) {
                                $this->_vars[$k] = "$v";
                            } else {
                                if ($v !== "''") {
                                    $this->_vars[$k] = "'$v'";
                                    $this->_vars[$k] = str_replace("''", "'", $this->_vars[$k]);
                                } else {
                                    $this->_vars[$k] = "$v";
                                }
                            }
                        } else {
                            $this->_vars[$k] = "$v";
                        }
                    } else if (is_null($v) || !is_value($v)) {
                        $this->_vars[$k] = "null";
                    }
                }

                $sql = "insert into " . $this->_table . " (" . join(", ", $keys) . ") values (" . join(", ", $this->_vars) . ");";
            } else {
                unset($this->{"crdate"});
                unset($this->{"createdate"});
                unset($this->{"crdt"});
                unset($this->{"cdt"});
                unset($this->{"crby"});
                unset($this->{"createby"});
                unset($this->{"cby"});
                unset($this->{"created_at"});
                unset($this->{"created_by"});
                unset($this->{"deldate"});
                unset($this->{"deletedate"});
                unset($this->{"dldt"});
                unset($this->{"ddt"});
                unset($this->{"dby"});
                unset($this->{"deleteby"});
                unset($this->{"dby"});
                unset($this->{"deleted_by"});

                unset($this->{"deleted_at"});

                $keys = array_keys($this->_vars);
                $raws = $this->_vars;

                foreach ($this->_vars as $k => $v) {
                    if (is_value($v)) {
                        if ($v != "now()" && $v != "current_date" && $v != "current_timestamp" && $v != "year(curdate())" && $v != "month(curdate())" && stripos($v, "(case when") === false && (substr($v, 0, 1) != "(" && substr($v, 1, -1) != ")")) {

                            if (substr($v, 0, 1) == "(" && substr($v, 1, -1) == ")" && strpos($v, "+") !== false) {
                                $this->_vars[$k] = "$k = $v";
                            } else {
                                if (preg_match("/^\(select(.*)\)/si", $v)) {
                                    $this->_vars[$k] = "$k = $v";
                                } else {
                                    if ($v !== "''") {
                                        $this->_vars[$k] = "$k = '$v'";
                                        $this->_vars[$k] = str_replace("''", "'", $this->_vars[$k]);
                                    } else {
                                        $this->_vars[$k] = "$k = $v";
                                    }
                                }
                            }
                        } else {
                            $this->_vars[$k] = "$k = $v";
                        }
                    } else if (is_null($v) || !is_value($v)) {
                        $this->_vars[$k] = "$k = null";
                    }
                }

                $sql = "SET @uuid := 0; update $this->_table set " . join(", ", $this->_vars) . ", id = (SELECT @uuid := id) where " . $sql_where;

            }

            if ($this->_passthrough) {
                if ($this->_debug_print) {
                    $_       = "";
                    $max_len = max(array_map('strlen', array_keys($this->_vars)));

                    foreach ($this->_vars as $k => $v) {
                        if (($this->_is_api || $this->_is_cli) || $this->_off_print_format) {
                            $_ .= "$k : $v" . PHP_EOL;
                        } else {
                            $_ .= $k . str_repeat("&nbsp;", $max_len - strlen($k)) . " : " . $v . "<br><hr style=\"padding-top: 5px; border:0px; border-top: 1px solid #dddbdb;\">";
                        }
                    }

                    $this->print_sql_format($sql, str_replace(",", "," . ((($this->_is_api || $this->_is_cli) || $this->_off_print_format) ? PHP_EOL : "<br>"), $_));
                }

                $stat = 1;
            } else {
                if ($this->_debug_print) {
                    if ($new_data) {
                        $_       = "";
                        $max_len = max(array_map('strlen', array_keys($this->_vars)));

                        foreach ($this->_vars as $k => $v) {
                            if (($this->_is_api || $this->_is_cli) || $this->_off_print_format) {
                                $_ .= "$k : $v" . PHP_EOL;
                            } else {
                                $_ .= $k . str_repeat("&nbsp;", $max_len - strlen($k)) . " : " . $v . "<br><hr style=\"padding-top: 5px; border:0px; border-top: 1px solid #dddbdb;\">";
                            }
                        }

                        $this->print_sql_format($sql, str_replace(",", "," . ((($this->_is_api || $this->_is_cli) || $this->_off_print_format) ? PHP_EOL : "<br>"), $_));
                        unset($_);
                    } else {

                        $this->print_sql_format($sql);
                    }

                    $stat      = 1;
                    $this->_id = 0;
                    if (!$this->_soft_update) {
                        exit;
                    }
                } else {
                    if ($this->_soft_update) {
                        if (!$this->_trx) {
                            $this->Begin();
                        }
                    }

                    if ($this->_show_sql) {
                        if ($new_data) {
                            $_       = "";
                            $max_len = max(array_map('strlen', array_keys($this->_vars)));

                            foreach ($this->_vars as $k => $v) {
                                if ($this->_is_api && $this->_is_api) {
                                    $_ .= "$k : $v" . PHP_EOL;
                                } else {
                                    $_ .= $k . str_repeat("&nbsp;", $max_len - strlen($k)) . " : " . $v . "<br><hr style=\"padding-top: 5px; border:0px; border-top: 1px solid #dddbdb;\">";
                                }
                            }

                            $this->print_sql_format($sql, str_replace(",", ",<br>", $_));
                            unset($_);
                        } else {
                            $this->print_sql_format($sql);
                        }
                    }

                    $this->execute($sql);

                    if ($new_data) {
                        $this->_id = $this->last_id();
                    } else {
                        $this->_id = (int) $this->read_one_sql("SELECT @ uuid;", \PDO::FETCH_COLUMN, 0);
                    }

                    $stat = !$this->is_error;

                    if ($this->_verify_sql) {
                        $_ = "";

                        $fetch = $this->read_all_sql("select * from " . $this->_table . " where id = " . $this->_id);
                        if (is_array($fetch) && count($fetch) > 0) {
                            $max_len = max(array_map('strlen', array_keys($fetch)));

                            foreach ($fetch as $k => $v) {
                                if ($this->_is_api && $this->_is_api) {
                                    $_ .= "$k : $v" . PHP_EOL;
                                } else {
                                    $_ .= $k . str_repeat("&nbsp;", $max_len - strlen($k)) . " : " . $v . "<br><hr style=\"padding-top: 5px; border:0px; border-top: 1px solid #dddbdb;\">";
                                }
                            }
                        }

                        if (!is_value($_)) {
                            $_ = "Unable to verify, no data can be selected from DB";
                        }

                        $this->print_sql_format($sql, str_replace(",", ",<br>", $_));
                        unset($fetch);
                        unset($_);
                    }

                    if (!$this->_soft_update) {
                        if ($new_data) {
                            $this->write_audit_log((isset($this->_raws['id']) ? (is_array($this->_raws['id']) ? (int) $this->_raws['id'][0] : (int) $this->_raws['id']) : (int) $this->_id), "C", null, $this->_raws);
                        } else {
                            $this->write_audit_log(($this->_id > 0 ? $this->_id : "@uuid"), "U", $this->_raws, $raws);

                        }
                    }

                }
            }

            unset($keys);
            unset($raws);
            $this->_vars                   = null;
            $this->_raws                   = null;
            $this->_where                  = "";
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

            if (is_value($this->_table_alias_temp)) {
                $this->_table_alias      = $this->_table_alias_temp;
                $this->_table_alias_temp = null;
            }

            return $stat;
        }
    }

    /**
     * Insert data into DB
     *
     * @return boolean
     */
    public function store()
    {
        if (is_array($this->_vars) && count($this->_vars) > 0) {
            $keys = array_keys($this->_vars);
            $raws = $this->_vars;
            unset($this->{"deldate"});
            unset($this->{"deletedate"});
            unset($this->{"dldt"});
            unset($this->{"ddt"});
            unset($this->{"dby"});
            unset($this->{"deleteby"});
            unset($this->{"dby"});
            unset($this->{"deleted_by"});

            unset($this->{"deleted_at"});
            unset($this->{"lupdate"});
            unset($this->{"ldt"});
            unset($this->{"lupdt"});
            unset($this->{"lupby"});
            unset($this->{"lupdateby"});
            unset($this->{"updatedate"});
            unset($this->{"updated_at"});
            unset($this->{"updated_by"});

            foreach ($this->_vars as $k => $v) {
                if (is_value($v)) {
                    if ($v != "now()" && $v != "current_date" && $v != "current_timestamp" && $v != "year(curdate())" && $v != "month(curdate())" && stripos($v, "(case when") === false && (substr($v, 0, 1) != "(" && substr($v, 1, -1) != ")")) {
                        if (strpos($v, "(") !== false && strpos($v, ")") !== false && strpos($v, "+") !== false) {
                            $this->_vars[$k] = "$v";
                        } else {
                            if ($v !== "''") {
                                $this->_vars[$k] = "'$v'";
                                $this->_vars[$k] = str_replace("''", "'", $this->_vars[$k]);
                            } else {
                                $this->_vars[$k] = "$v";
                            }
                        }
                    } else {
                        $this->_vars[$k] = "$v";
                    }
                } else if (is_null($v) || !is_value($v)) {
                    $this->_vars[$k] = "null";
                }
            }

            if (!is_array($this->_raws)) {
                $this->_raws = &$raws;
            }

            $sql = "insert into " . $this->_table . " (" . join(", ", $keys) . ") values (" . join(", ", $this->_vars) . ");";

            if ($this->_passthrough) {
                $stat = 1;
            } else {
                if ($this->_debug_print) {
                    $_       = "";
                    $max_len = max(array_map('strlen', array_keys($this->_vars)));

                    foreach ($this->_vars as $k => $v) {
                        if ($this->_is_api && $this->_is_cli) {
                            $_ .= "$k : $v" . PHP_EOL;
                        } else {
                            $_ .= $k . str_repeat("&nbsp;", $max_len - strlen($k)) . " : " . $v . "<br><hr style=\"padding-top: 5px; border:0px; border-top: 1px solid #dddbdb;\">";
                        }
                    }

                    $this->print_sql_format($sql, str_replace(",", ",<br>", $_));
                    unset($_);

                    $stat      = 1;
                    $this->_id = 0;
                    if (!$this->_soft_update) {
                        exit;
                    }
                } else {
                    if ($this->_soft_update) {
                        if (!$this->_trx) {
                            $this->begin();
                        }
                    }

                    if ($this->_show_sql) {
                        $_       = "";
                        $max_len = max(array_map('strlen', array_keys($this->_vars)));

                        foreach ($this->_vars as $k => $v) {
                            if ($this->_is_api && $this->_is_cli) {
                                $_ .= "$k : $v" . PHP_EOL;
                            } else {
                                $_ .= $k . str_repeat("&nbsp;", $max_len - strlen($k)) . " : " . $v . "<br><hr style=\"padding-top: 5px; border:0px; border-top: 1px solid #dddbdb;\">";
                            }
                        }

                        $this->print_sql_format($sql, str_replace(",", ",<br>", $_));
                        unset($_);
                    }

                    $this->execute($sql);
                    $this->_id = $this->last_id();
                    $stat      = !$this->is_error;

                    if ($this->_verify_sql) {
                        $_ = "";

                        $fetch = $this->read_all_sql("select * from " . $this->_table . " where id = " . $this->_id, \PDO::FETCH_ASSOC);
                        if (is_array($fetch) && count($fetch) > 0) {
                            $max_len = max(array_map('strlen', array_keys($fetch)));

                            foreach ($fetch as $k => $v) {
                                if ($this->_is_api && $this->_is_cli) {
                                    $_ .= "$k : $v" . PHP_EOL;
                                } else {
                                    $_ .= $k . str_repeat("&nbsp;", $max_len - strlen($k)) . " : " . $v . "<br><hr style=\"padding-top: 5px; border:0px; border-top: 1px solid #dddbdb;\">";
                                }
                            }
                        }

                        if (!is_value($_)) {
                            $_ = "Unable to verify, no data can be selected from DB";
                        }

                        $this->print_sql_format($sql, str_replace(",", ",<br>", $_));
                        unset($fetch);
                        unset($_);
                    }

                    if (!$this->_soft_update) {
                        $this->write_audit_log((isset($this->_raws['id']) ? (is_array($this->_raws['id']) ? (int) $this->_raws['id'][0] : (int) $this->_raws['id']) : (int) $this->_id), "C", null, $this->_raws);
                    }
                }
            }

            unset($keys);
            unset($raws);
            $this->_vars                   = null;
            $this->_raws                   = null;
            $this->_where                  = "";
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

            if (is_value($this->_table_alias_temp)) {
                $this->_table_alias      = $this->_table_alias_temp;
                $this->_table_alias_temp = null;
            }

            return $stat;
        }
    }

    /**
     * Update data into DB
     *
     * @param string $sql_where SQL where statement.  e.g: id = 123 and userid='admin'
     * @return array
     */
    public function update($sql_where = null)
    {
        if (is_array($sql_where)) {
            foreach ($sql_where as $k => $v) {
                $this->_vars[$k] = $v;
            }

            $sql_where = null;
        }

        if (is_array($this->_vars) && count($this->_vars) > 0) {
            $keys = array_keys($this->_vars);
            $raws = $this->_vars;

            if ($sql_where === null) {
                if (!is_value($this->_where)) {
                    $sql_where = "crby = '" . $this->_user . "' and id = " . (int) $this->_id;
                } else {
                    $sql_where = $this->_where;
                }
            }else{
                if ((int)$sql_where > 0){
                    $this->by_id($sql_where);
                    $sql_where = $this->_where;
                }
            }

            if (isset($this->_vars['status']) && $this->_vars['status'] == "I") {
                unset($this->{"lupdate"});
                unset($this->{"ldt"});
                unset($this->{"lupdt"});
                unset($this->{"lupby"});
                unset($this->{"lupdateby"});
                unset($this->{"updatedate"});
                unset($this->{"updated_at"});
                unset($this->{"updated_by"});
            } else {
                unset($this->{"deldate"});
                unset($this->{"deletedate"});
                unset($this->{"dldt"});
                unset($this->{"ddt"});
                unset($this->{"dby"});
                unset($this->{"deleteby"});
                unset($this->{"dby"});
                unset($this->{"deleted_by"});
                unset($this->{"deleted_at"});
            }

            unset($this->{"crdate"});
            unset($this->{"createdate"});
            unset($this->{"crdt"});
            unset($this->{"cdt"});
            unset($this->{"crby"});
            unset($this->{"createby"});
            unset($this->{"cby"});
            unset($this->{"created_at"});
            unset($this->{"created_by"});

            foreach ($this->_vars as $k => $v) {
                if (is_value($v)) {
                    if ($v != "now()" && $v != "current_date" && $v != "current_timestamp" && $v != "year(curdate())" && $v != "month(curdate())" && stripos($v, "(case when") === false && (substr($v, 0, 1) != "(" && substr($v, 1, -1) != ")")) {
                        if (strpos($v, "(") !== false && strpos($v, ")") !== false && strpos($v, "+") !== false) {
                            $this->_vars[$k] = "$k = $v";
                        } else {
                            if (preg_match("/^\(select(.*)\)/si", $v)) {
                                $this->_vars[$k] = "$k = $v";
                            } else {
                                if ($v !== "''") {
                                    $this->_vars[$k] = "$k = '$v'";
                                    $this->_vars[$k] = str_replace("''", "'", $this->_vars[$k]);
                                } else {
                                    $this->_vars[$k] = "$k = $v";
                                }
                            }
                        }
                    } else {
                        $this->_vars[$k] = "$k = $v";
                    }
                } else if (is_null($v) || !is_value($v)) {
                    $this->_vars[$k] = "$k = null";
                }
            }

            if ($this->_id > 0) {
                $sql = "update $this->_table set " . join(", ", $this->_vars) . " where " . $sql_where;
            } else {
                $sql = "SET @uuid := 0; update $this->_table set " . join(", ", $this->_vars) . ", id = (SELECT @uuid := id) where " . $sql_where;
            }

            if ($this->_passthrough) {
                if ($this->_debug_print) {
                    $this->print_sql_format($sql);
                }

                $stat = 1;
            } else {
                if ($this->_debug_print) {
                    $this->print_sql_format($sql);
                    $stat = 1;
                    if (!$this->_soft_update) {
                        exit;
                    }
                } else {
                    if ($this->_enable_logger) {
                        $this->set_try_catch();
                        $this->_raws = $this->findBy(null, $sql_where, join(", ", $keys));

                        if (!is_array($this->_raws) && is_null($this->_raws)) {
                            return false;
                        }
                    }

                    if ($this->_show_sql) {
                        $this->print_sql_format($sql);
                    }

                    $this->update_sql($sql);
                    $stat = (bool) !$this->is_error;

                    if ($this->_verify_sql) {
                        $_ = "";

                        $fetch = $this->read_one_sql("select * from " . $this->_table . " where " . $sql_where, \PDO::FETCH_ASSOC);
                        if (is_array($fetch) && count($fetch) > 0) {
                            $max_len = max(array_map('strlen', array_keys($fetch)));

                            foreach ($fetch as $k => $v) {
                                if ($this->_is_api && $this->_is_cli) {
                                    $_ .= "$k : $v" . PHP_EOL;
                                } else {
                                    $_ .= $k . str_repeat("&nbsp;", $max_len - strlen($k)) . " : " . $v . "<br><hr style=\"padding-top: 5px; border:0px; border-top: 1px solid #dddbdb;\">";
                                }
                            }
                        }

                        if (!is_value($_)) {
                            $_ = "Unable to verify, no data can be selected from DB";
                        }

                        $this->print_sql_format($sql, str_replace(",", ",<br>", $_));
                        unset($fetch);
                        unset($_);
                    }

                    if (!$this->_trx && !$this->_soft_update) {
                        $this->write_audit_log(($this->_id > 0 ? $this->_id : "@uuid"), "U", $this->_raws, $raws);
                    }
                }
            }

            unset($keys);
            unset($raws);
            $this->_vars                   = null;
            $this->_raws                   = null;
            $this->_where                  = "";
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

            if (is_value($this->_table_alias_temp)) {
                $this->_table_alias      = $this->_table_alias_temp;
                $this->_table_alias_temp = null;
            }

            return $stat;
        }
    }

    /**
     * Update data into DB with SQL
     *
     * @param string $sql
     * @return int Number of affected rows to be updated
     */
    public function update_sql($sql)
    {
        if (!empty($this->instance)) {
            $rs                  = $this->instance->prepare($sql);
            $this->affected_Rows = $rs->execute();
            $this->error($rs);

            unset($rs);
            return $this->affected_Rows;
        }
    }

    #################################################### Private ###############################################
}
