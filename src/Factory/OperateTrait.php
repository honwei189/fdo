<?php
/*
 * Created       : 2019-05-12 05:43:32 pm
 * Author        : Gordon Lim <honwei189@gmail.com>
 * Last Modified : 2020-11-01 01:56:21 pm
 * Modified By   : Gordon Lim
 * ---------
 * Changelog
 *
 * Date & time           By                    Version   Comments
 * -------------------   -------------------   -------   ---------------------------------------------------------
 * 2020-11-01 01:49 pm   Gordon Lim            1.0.2     Added new feature -- get SQL.  Allows to get SQL instead of send SQL to database
 * 2020-08-25 08:27 pm   Gordon Lim            1.0.1     Rectified incompatitable function in mySQL 8 problem
 *
 */

namespace honwei189\FDO\Factory;

/**
 *
 * DB operation function
 *
 *
 * @package     FDO
 * @subpackage
 * @author      Gordon Lim <honwei189@gmail.com>
 * @link        https://github.com/honwei189/fdo/
 * @version     "1.0.2" Added new feature -- get SQL.  Allows to get SQL instead of send SQL to database
 * @since       "1.0.1" Rectified incompatitable function in mySQL 8 problem
 */
trait OperateTrait
{
    public $fillable;
    private $is_nofillable = false;
    private $_vars         = [];

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
                $sql_where = ($this->user_only && is_int($this->user_id) ? "created_by = " . $this->user_id : "") . " and id = " . (int) $this->_id;
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

        if ($this->_get_sql) {
            return $sql;
        }

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

            if ($this->is_enabled_crud_log() && !$this->_soft_update) {
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
                $this->error($rs, $sql);

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
                $this->error($rs, $sql);
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
                $this->error($e, $sql);
                unset($error);
                unset($except);
            }
        }
    }

    /**
     * Mass assignment data properties by using array notation.
     *
     * It will check from model's $fillable attribute to determine what properties should allowed stores into DB
     *
     * Example :
     *
     * $fdo->fill(["name" => "Tester", "email" => "tester@example"]);
     *
     * or;
     *
     * FDOM::fill(["name" => "Tester", "email" => "tester@example"]);
     *
     * @param array $dataset Dataset (key and value) to insert into database.  e.g: $_POST or $dataset = ["name" => "Tester", "email" => "tester@example"]
     * @param array $excludes Ignore the key and do not save into database.  e.g: ["name", "gender"]
     * @return FDO
     */
    public function fill(array $dataset, array $excludes = null)
    {
        if (is_array($excludes) && count($excludes) > 0) {
            $excludes = array_flip($excludes);
        }

        if (!$this->is_laravel && (!is_array($this->fillable) || (is_array($this->fillable) && count($this->fillable) == 0))) {
            $this->is_nofillable = true;
        } else if ($this->is_laravel && !is_value($this->parent)) {
            $this->is_nofillable = true;
        }

        if (!$this->is_nofillable) {
            if (is_array($this->fillable) && count($this->fillable) > 0) {
                $match   = false;
                $dataset = (object) $dataset;

                foreach ($this->fillable as $v) {
                    if (property_exists($dataset, $v) && !isset($excludes[$v])) {
                        $this->_vars[$v] = $dataset->$v;
                        $match           = true;
                    }
                }

                if (!$match) {
                    die($this->print_sql_format("No data columns matched with " . (str($this->parent) ? $this->parent . " ->" : "") . "\$fillable - " . implode(", ", $this->fillable)));
                }
            } else {
                die($this->print_sql_format("No \$fillable found" . (str($this->parent) ? " from " . $this->parent : "")));
            }

            return $this;
        }

        if (isset($dataset) && is_array($dataset) && count($dataset) > 0) {
            $this->_vars = [];

            foreach ($dataset as $k => $v) {
                switch ($k) {
                    case "_token":
                    case "_method":
                    case "fillable":
                        break;

                    default:
                        if (!isset($excludes[$k])) {
                            $this->_vars[$k] = $v;
                        }
                        break;
                }
            }
        } else {
            die($this->print_sql_format("No passing any data columns to save into database"));
        }

        return $this;
    }

    /**
     * Declare the ORM key mapping and save data into the database.
     * 
     * or;
     * 
     * Replace the original inserted key name (e.g: $_POST['my_name']) to another ( e.g: display_name ) and save into database.
     * 
     *
     * Example: $_POST['name'], in DB it is "display_name", if using fill(), it will save to column "name" instead of currect name = "display_name"
     *
     * insert into aaa (name) values ...
     *
     * After use fillmap(), it will save to column "display_name"
     *
     * insert into aaa (display_name) values ...
     *
     *
     * Format :
     *
     * [ RAW_or_original_name => REAL_NAME_in_DB ]
     *
     * RAW_or_original_name = Input name / $_POST[ANY_NAME]
     * REAL_NAME_in_DB = Real name in DB.  If leave it as blank, skip insert this into DB
     *
     * e.g: ["g" => "gender", "name" => "display_name", "email" => ""];
     *
     * Key = $_POST['g'], $_POST['name'], Value = insert into aaa (gender, display_name) values ...
     *
     * @param array $keymap e.g: ["g" => "gender", "name" => "display_name"]
     * @param array $dataset Dataset (key and value) to insert into database.  e.g: $_POST or $dataset =["g" => "M", "name" => "Name"]; $_POST
     * @param array $excludes Ignore the key (mapped key name.  e.g: $keymaps = ["g" => "gender"], $excludes = ["gender"]) and do not save into database.  e.g: ["name", "gender"]
     * @return FDO
     */
    public function fillmap(array $keymaps, array $dataset = null, array $excludes = null)
    {
        $this->nofillable();

        if (!is_array($dataset)) {
            if (count($this->_vars) > 0) {
                $dataset = $this->_vars;
            } else {
                $dataset = $this->_post;
            }
        }

        $mapped = [];

        foreach ($dataset as $k => $v) {
            if (isset($keymaps[$k])) {
                if (str($keymaps[$k])) {
                    unset($dataset[$k]);
                    $mapped[$keymaps[$k]] = $v;
                }
            } else {
                $mapped[$k] = $v;
            }
        }

        $dataset = &$mapped;
        unset($mapped);

        return $this->fill($dataset, $excludes);
    }

    /**
     * Save dataset data (maybe the dataset is huge) into database based on the specified / selected / custom data columns -- $cols
     *
     * e.g:
     *
     * $dataset = $_POST or array("name" => "Tester", "email" => "tester@example", "gender" => "M")
     * $cols_only = ["name", "gender"];
     *
     * From above example, only "name" and "gender" will be save into database whereas "email" will be ignored
     *
     * @param array $dataset Dataset (key and value) to insert into database.  e.g: $_POST or $dataset = ["name" => "Tester", "email" => "tester@example", "gender" => "M"]
     * @param array $cols_only Data columns name to fill in database. e.g: ["name", "gender"]
     * @return FDO
     */
    public function fillonly(array $dataset, array $cols_only)
    {
        // $fillable = false;
        // if ($this->is_nofillable) {
        //     $fillable = true;
        //     $this->nofillable(false);
        // }

        // $this->fillable = $cols_only;

        // $this->fill($dataset);

        // if ($fillable) {
        //     $this->nofillable(false);
        //     $fillable = false;
        // }

        $match   = false;
        $dataset = (object) $dataset;

        foreach ($cols_only as $v) {
            if (property_exists($dataset, $v) && !isset($excludes[$v])) {
                $this->_vars[$v] = $dataset->$v;
                $match           = true;
            }
        }

        if (!$match) {
            die($this->print_sql_format("No data columns matched with \$cols_only - " . implode(", ", $cols_only)));
        }

        return $this;
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
     * Ignore $fillable
     *
     * @param bool $bool Default is true
     * @return FDO
     */
    public function nofillable(bool $bool = true)
    {
        $this->is_nofillable = $bool;

        return $this;
    }

    /**
     * Ignore $fillable.  Alias of nofillable()
     *
     * @param bool $bool Default is true
     * @return FDO
     */
    public function notfillable(bool $bool = true)
    {
        return $this->nofillable($bool);
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
                    $sql_where = ($this->user_only && is_int($this->user_id) ? "created_by = " . $this->user_id : "") . " and id = " . (int) $this->_id;
                } else {
                    $sql_where = $this->_where;
                }
            } else {
                if ((int) $sql_where > 0) {
                    $this->by_id($sql_where);
                    $sql_where = $this->_where;
                }
            }

            if (!is_value($sql_where)) {
                $new_data = true;
            } else {
                $_debug_print       = $this->_debug_print;
                $this->_debug_print = false;
                $this->_raws        = $this->debug(false)->findBy(null, $sql_where, join(", ", array_keys($this->_vars)));
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

                // foreach ($this->_vars as $k => $v) {
                //     if (str($v)) {
                //         if ($v != "now()" && $v != "current_date" && $v != "current_timestamp" && $v != "year(curdate())" && $v != "month(curdate())" && stripos($v, "(case when") === false && (substr($v, 0, 1) != "(" && substr($v, 1, -1) != ")")) {

                //             if (substr($v, 0, 1) == "(" && substr($v, 1, -1) == ")" && strpos($v, "+") !== false) {
                //                 $this->_vars[$k] = "$v";
                //             } else {
                //                 if ($v !== "''") {
                //                     $this->_vars[$k] = "'$v'";
                //                     $this->_vars[$k] = str_replace("''", "'", $this->_vars[$k]);
                //                 } else {
                //                     $this->_vars[$k] = "$v";
                //                 }
                //             }
                //         } else {
                //             $this->_vars[$k] = "$v";
                //         }
                //     } else {
                //         if (is_numeric($v) || is_bool($v)) {
                //             $this->_vars[$k] = $v;
                //         } else {
                //             $this->_vars[$k] = "null";
                //         }
                //     }
                // }

                $this->build_store_attributes("insert");

                $sql = "insert into " . $this->_table . " (" . join(", ", $keys) . ") values (" . join(", ", $this->_vars) . ");";

                // unset($attrs);
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

                // foreach ($this->_vars as $k => $v) {
                //     if (str($v)) {
                //         if ($v != "now()" && $v != "current_date" && $v != "current_timestamp" && $v != "year(curdate())" && $v != "month(curdate())" && stripos($v, "(case when") === false && (substr($v, 0, 1) != "(" && substr($v, 1, -1) != ")")) {

                //             if (substr($v, 0, 1) == "(" && substr($v, 1, -1) == ")" && strpos($v, "+") !== false) {
                //                 $this->_vars[$k] = "$k = $v";
                //             } else {
                //                 if (preg_match("/^\(select(.*)\)/si", $v)) {
                //                     $this->_vars[$k] = "$k = $v";
                //                 } else {
                //                     if ($v !== "''") {
                //                         $this->_vars[$k] = "$k = '$v'";
                //                         $this->_vars[$k] = str_replace("''", "'", $this->_vars[$k]);
                //                     } else {
                //                         $this->_vars[$k] = "$k = $v";
                //                     }
                //                 }
                //             }
                //         } else {
                //             $this->_vars[$k] = "$k = $v";
                //         }
                //     } else {
                //         if (is_numeric($v) || is_bool($v)) {
                //             $this->_vars[$k] = "$k = $v";
                //         } else {
                //             $this->_vars[$k] = "$k = null";
                //         }
                //     }
                // }

                // $sql = "SET @uuid := 0; update $this->_table set " . join(", ", $this->_vars) . ", id = (SELECT @uuid := id) where " . $sql_where;

                // $sql = "update $this->_table set " . join(", ", $this->_vars) . ", id = (SELECT @uuid := id) where " . $sql_where;

                $this->build_store_attributes("update");

                $sql = "update $this->_table set " . join(", ", $this->_vars) . ", id = LAST_INSERT_ID(id) where " . $sql_where;
            }

            if ($this->_get_sql) {
                return $sql;
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

                    // $this->execute("SET @uuid := 0;");
                    $this->execute($sql);

                    if ($new_data) {
                        $this->_id = $this->last_id();
                    } else {
                        // $this->_id = (int) $this->read_one_sql("SELECT @uuid;", \PDO::FETCH_COLUMN, 0);
                        $this->_id = (int) $this->read_one_sql("SELECT LAST_INSERT_ID();", \PDO::FETCH_COLUMN, 0);

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

                    if ($this->is_enabled_crud_log() && !$this->_soft_update) {
                        if ($new_data) {
                            $this->write_audit_log((isset($this->_raws['id']) ? (is_array($this->_raws['id']) ? (int) $this->_raws['id'][0] : (int) $this->_raws['id']) : (int) $this->_id), "C", null, $this->_raws);
                        } else {
                            // $this->write_audit_log(($this->_id > 0 ? $this->_id : "@uuid"), "U", $this->_raws, $raws);
                            $this->write_audit_log(($this->_id > 0 ? $this->_id : "LAST_INSERT_ID()"), "U", $this->_raws, $raws);

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

            // foreach ($this->_vars as $k => $v) {
            //     if (str($v)) {
            //         if ($v != "now()" && $v != "current_date" && $v != "current_timestamp" && $v != "year(curdate())" && $v != "month(curdate())" && stripos($v, "(case when") === false && (substr($v, 0, 1) != "(" && substr($v, 1, -1) != ")")) {
            //             if (strpos($v, "(") !== false && strpos($v, ")") !== false && strpos($v, "+") !== false) {
            //                 $this->_vars[$k] = "$v";
            //             } else {
            //                 if ($v !== "''") {
            //                     $this->_vars[$k] = "'$v'";
            //                     $this->_vars[$k] = str_replace("''", "'", $this->_vars[$k]);
            //                 } else {
            //                     $this->_vars[$k] = "$v";
            //                 }
            //             }
            //         } else {
            //             $this->_vars[$k] = "$v";
            //         }
            //     } else {
            //         if (is_numeric($v) || is_bool($v)) {
            //             $this->_vars[$k] = $v;
            //         } else {
            //             $this->_vars[$k] = "null";
            //         }
            //     }
            // }

            $this->build_store_attributes("insert");

            if (!is_array($this->_raws)) {
                $this->_raws = &$raws;
            }

            $sql = "insert into " . $this->_table . " (" . join(", ", $keys) . ") values (" . join(", ", $this->_vars) . ");";

            if ($this->_get_sql) {
                return $sql;
            }

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

                    if ($this->is_enabled_crud_log() && !$this->_soft_update) {
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
            // foreach ($sql_where as $k => $v) {
            //     $this->_vars[$k] = $v;
            // }

            $this->fill($sql_where);
            $sql_where = null;
        }

        if (is_array($this->_vars) && count($this->_vars) > 0) {
            $keys = array_keys($this->_vars);
            $raws = $this->_vars;

            if ($sql_where === null) {
                if (!str($this->_where)) {
                    $sql_where = ($this->user_only && is_int($this->user_id) ? "created_by = " . $this->user_id . " and " : "") . "id = " . (int) $this->_id;
                } else {
                    $sql_where = $this->_where;
                }
            } else {
                if ((int) $sql_where > 0) {
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
            unset($this->{"fillable"});
            unset($this->_vars['fillable']);

            // foreach ($this->_vars as $k => $v) {
            //     if (str($v)) {
            //         if ($v != "now()" && $v != "current_date" && $v != "current_timestamp" && $v != "year(curdate())" && $v != "month(curdate())" && stripos($v, "(case when") === false && (substr($v, 0, 1) != "(" && substr($v, 1, -1) != ")")) {
            //             if (strpos($v, "(") !== false && strpos($v, ")") !== false && strpos($v, "+") !== false) {
            //                 $this->_vars[$k] = "$k = $v";
            //             } else {
            //                 if (preg_match("/^\(select(.*)\)/si", $v)) {
            //                     $this->_vars[$k] = "$k = $v";
            //                 } else {
            //                     if ($v !== "''") {
            //                         $this->_vars[$k] = "$k = '$v'";
            //                         $this->_vars[$k] = str_replace("''", "'", $this->_vars[$k]);
            //                     } else {
            //                         $this->_vars[$k] = "$k = $v";
            //                     }
            //                 }
            //             }
            //         } else {
            //             $this->_vars[$k] = "$k = $v";
            //         }
            //     } else {
            //         if (is_numeric($v) || is_bool($v)) {
            //             $this->_vars[$k] = "$k = $v";
            //         } else {
            //             $this->_vars[$k] = "$k = null";
            //         }
            //     }
            // }

            $this->build_store_attributes("update");

            if ($this->_id > 0) {
                $sql = "update $this->_table set " . join(", ", $this->_vars) . " where " . $sql_where;
            } else {
                // $sql = "SET @uuid := 0; update $this->_table set " . join(", ", $this->_vars) . ", id = (SELECT @uuid := id) where " . $sql_where;
                $sql = "update $this->_table set " . join(", ", $this->_vars) . ", id = LAST_INSERT_ID(id) where " . $sql_where;
            }

            if ($this->_get_sql) {
                return $sql;
            }

            $sql_print = "";
            $max_len   = max(array_map('strlen', array_keys($raws)));

            foreach ($raws as $k => $v) {
                if ($this->_is_api && $this->_is_cli) {
                    $sql_print .= "$k : $v" . PHP_EOL;
                } else {
                    $sql_print .= $k . str_repeat("&nbsp;", $max_len - strlen($k)) . " : " . $v . "<br><hr style=\"padding-top: 5px; border:0px; border-top: 1px solid #dddbdb;\">";
                }
            }

            if ($this->_passthrough) {
                if ($this->_debug_print) {
                    $this->print_sql_format($sql, str_replace(",", ",<br>", $sql_print));
                }

                $stat = 1;
            } else {
                if ($this->_debug_print) {
                    $this->print_sql_format($sql, str_replace(",", ",<br>", $sql_print));
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
                        $this->print_sql_format($sql, str_replace(",", ",<br>", $sql_print));
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

                    if ($this->is_enabled_crud_log() && !$this->_trx && !$this->_soft_update) {
                        // $this->write_audit_log(($this->_id > 0 ? $this->_id : "@uuid"), "U", $this->_raws, $raws);
                        $this->write_audit_log(($this->_id > 0 ? $this->_id : "LAST_INSERT_ID()"), "U", $this->_raws, $raws);
                    }
                }
            }

            unset($keys);
            unset($raws);
            unset($sql_print);
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
            $this->error($rs, $sql);

            unset($rs);
            return $this->affected_Rows;
        }
    }

    #################################################### Private ###############################################

    private function build_store_attributes($action)
    {
        foreach ($this->_vars as $k => $v) {
            $this->_vars[$k] = ($action == "update" ? "$k = " : "") . $this->process_data_attribute($v);

            // if (str($v)) {
            //     // $value = $v;
            //     // if (stripos($v, "(case when") === false && (substr($v, 0, 1) != "(" && substr($v, 1, -1) != ")")) {
            //     //     $value = "special prefix bracket";
            //     // }

            //     switch ($v) {
            //         // case "now()":
            //         // case "current_date":
            //         // case "current_date()":
            //         // case "current_timestamp":
            //         // case "current_timestamp()":
            //         // case "year(curdate())":
            //         // case "month(curdate())":
            //         // case "''":
            //         // // case (preg_match('/\s*\((^|\s|\b)(?!case\s\S)(.*?)\)\s*+/siU', $v) ? true : false):
            //         case (preg_match('/\s*\((^|\s|\b)(case when\s\S)(.*?)\)\s*+/siU', $v) ? true : false):
            //         case (preg_match("/^\(\w+\)($|\b)|(\w+)\(\)|^\w+\((?!case\s\S)(.*?)\)$/isU", $v) ? true : false):
            //             // case (preg_match("/^\(\w+\)($|\b)|(\w+)\(\)/isU", $v) ? true : false):
            //             // case (preg_match('/(adddate|addtime|convert_tz|date_add|date_format|date_diff|date_sub|day|dayname|dayofmonth|dayofweek|dayofyear|extract|from_days|from_unixtime|get_format|hour|last_day|makedate|maketime|microsecond|minute|month|monthname|period_add|period_diff|quarter|sec_to_time|second|str_to_date|subdate|subtime|time|time_format|time_to_sec|timediff|timestamp|timestampadd|timestampdiff|to_days|to_seconds|unix_timestamp|utc_date|utc_time|utc_timestamp|week|yearweek|weekday|weekofyear|year|yearweek)\((.?)\)($|\s|\b)/siU', $v) ? true : false):
            //             // case (preg_match('/\s*\((^|\s|\b)(?!case\s\S)(.*?)\)\s*+/siU', $v) ? true : false):
            //             // case (preg_match("/(?:(?:^(?!.*\bcase\b)|\G(?!\A)).*?)\K\b(?:\(|\))\b/gm", $v, $reg) ? true : false):

            //             // if (substr($v, 0, 1) == "(" && substr($v, 1, -1) == ")" && strpos($v, "+") !== false) {
            //             //     $this->_vars[$k] = ($action == "update" ? "$k = " : "") . "$v";
            //             // } else {
            //             //     if ($v !== "''") {
            //             //         $this->_vars[$k] = ($action == "update" ? "$k = " : "") . "'$v'";
            //             //         $this->_vars[$k] = str_replace("''", "'", $this->_vars[$k]);
            //             //     } else {
            //             //         $this->_vars[$k] = ($action == "update" ? "$k = " : "") . "$v";
            //             //     }
            //             // }

            //             $this->_vars[$k] = ($action == "update" ? "$k = " : "") . "$v";
            //             break;

            //         default:
            //             $this->_vars[$k] = ($action == "update" ? "$k = " : "") . ($v == "''" ? "''" : "$v");
            //             break;
            //     }

            //     // if ($v != "now()" && $v != "current_date" && $v != "current_timestamp" && $v != "year(curdate())" && $v != "month(curdate())" && stripos($v, "(case when") === false && (substr($v, 0, 1) != "(" && substr($v, 1, -1) != ")")) {

            //     //     if (substr($v, 0, 1) == "(" && substr($v, 1, -1) == ")" && strpos($v, "+") !== false) {
            //     //         $this->_vars[$k] = "$v";
            //     //     } else {
            //     //         if ($v !== "''") {
            //     //             $this->_vars[$k] = "'$v'";
            //     //             $this->_vars[$k] = str_replace("''", "'", $this->_vars[$k]);
            //     //         } else {
            //     //             $this->_vars[$k] = "$v";
            //     //         }
            //     //     }
            //     // } else {
            //     //     $this->_vars[$k] = "$v";
            //     // }
            // } else {
            //     if (is_numeric($v) || is_bool($v)) {
            //         $this->_vars[$k] = ($action == "update" ? "$k = " : "") . $v;
            //     } else {
            //         $this->_vars[$k] = ($action == "update" ? "$k = " : "") . "null";
            //     }
            // }
        }
    }
}
