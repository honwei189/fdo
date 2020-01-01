<?php
/*
 * @creator           : Gordon Lim <honwei189@gmail.com>
 * @created           : 12/05/2019 17:08:39
 * @last modified     : 23/12/2019 21:44:01
 * @last modified by  : Gordon Lim <honwei189@gmail.com>
 */

namespace honwei189\fdo;

/**
 *
 * HTTP form data into SQL
 *
 *
 * @package     fdo
 * @subpackage
 * @author      Gordon Lim <honwei189@gmail.com>
 * @link        https://github.com/honwei189/html/
 * @link        https://appsw.dev
 * @link        https://justtest.app
 * @version     "1.0.0" 
 * @since       "1.0.0" 
 */
trait form
{
    /**
     * @access private
     * @internal
     */
    public function __construct()
    {
    }

    /**
     * Turn $_GET[KEY_NAME] into SQL
     *
     * @param string $input_key_name $_GET key name
     * @param string $type Variable data type.  e.g:  crypt, date, number, like (SQL search by like)
     * @return form
     */
    public function and_get($input_key_name = null, $type = "")
    {
        $value = "";

        if (is_null($input_key_name)) {
            foreach ($this->_get as $k => $v) {
                if (stripos($k, "sdate") === false || stripos($k, "edate") === false || stripos($k, "stime") === false || stripos($k, "etime") === false) {
                    if ($this->is_value($v)) {
                        $v = value_format($v, $type);

                        if ($type == "like") {
                            $this->where("trim(lower($k)) like '%" . trim(strtolower($v)) . "%'");
                        } else {
                            $this->where("$k='$v'");
                        }

                    }
                }
            }

            return $this;
        } else if (is_array($input_key_name)) {
            foreach ($input_key_name as $k => $v) {
                if (stripos($v, "sdate") === false || stripos($v, "edate") === false || stripos($v, "stime") === false || stripos($v, "etime") === false) {
                    if ($this->is_value($v)) {
                        $value = value_format($value, $type);
                        $this->where("$v='$value'");
                    }
                }
            }

            return $this;
        }

        $value = $this->inputs($input_key_name, $type);

        if ($this->is_value($value)) {
            if ($type == "like") {
                $this->where("trim(lower($input_key_name)) like '%" . trim(strtolower($value)) . "%'");
            } else {
                $this->where("$input_key_name='$value'");
            }
        }

        return $this;
    }

    /**
     * SQL AND from HTML FORM POST input
     *
     * Usage :
     *
     * $this->and_input("form_object_name", "db_table_column_name", "like"); // output = select * from example where db_table_column name like '%form_object_value%'
     *
     * or;
     *
     * $this->and_input("form_object_name", [
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
     * $this->and_input("", [
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
     * @return form
     */
    public function and_input($input_key_name, $column_name, $type = "")
    {
        $value = "";

        if (is_array($column_name) && count($column_name) > 0) {
            foreach ($column_name as $k => $v) {
                if (is_value($input_key_name)) {
                    $value = $this->value_format($v, $type);
                    $value = str_replace("{$input_key_name}", $this->_post[$input_key_name], $value);

                    if (is_value($value)) {
                        if ($type == "like") {
                            $this->where("trim(lower($column_name)) like '%" . trim(strtolower($value)) . "%'");
                        } else {
                            $this->where("$column_name='$value'");
                        }
                    }
                } else {
                    $value = $this->value_format($v, $type);
                    preg_match("/\{(.*?)\}/si", $value, $reg);

                    if (is_array($reg) && count($reg) > 1) {
                        if (isset($this->_post[$reg[1]]) && is_value($this->_post[$reg[1]])) {
                            $value = str_replace("{" . $reg[1] . "}", $this->_post[$reg[1]], $value);
                        } else {
                            $value = "";
                        }
                    }

                    $reg = null;

                    if (is_value($value)) {
                        $this->where($value);
                    }
                }
            }
        } else {
            if (is_value($input_key_name)) {
                $value = $this->value_format($this->inputs($input_key_name, $type), $type);

                if (isset($this->_post[$input_key_name])) {
                    $value = str_replace("{$input_key_name}", $this->_post[$input_key_name], $value);
                }

                if (is_value($value)) {
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
                    if (isset($this->_post[$reg[1]]) && is_value($this->_post[$reg[1]])) {
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
     * Generate WHERE SQL from custom data inputs.
     *
     * e.g :
     *
     * $data = ["cat" => 1, "status" => "A", "nums" => 10];
     *
     * $this->and_keys(["cat", "status"], $data);
     *
     * output :
     *
     * select * from xxx where cat = 1 and status = 'A'
     *
     * @param array|string $keys $_POST key name
     * @param string $raws $_POST
     * @return form
     */
    public function and_keys($keys, &$raws)
    {
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        foreach ($keys as $k => $v) {
            if (isset($raws[$v])) {
                $this->and($v, $raws[$v]);
            }
        }

        return $this;
    }

    /**
     * Generate WHERE statement from FORM POST.
     *
     * e.g :
     *
     * $this->and_post("date");
     *
     * $this->and_post(["cat", "status"]);
     *
     * output :
     *
     * select * from xxx where date = $_POST['date'] and cat = $_POST['cat] and status = $_POST['status]
     *
     * @param array|string $keys $_POST key name
     * @param string $type Variable data type.  e.g:  crypt, date, number, like (SQL search by like)
     * @return form
     */
    public function and_post($input_key_name = null, $type = "")
    {
        $value = "";

        if (is_null($input_key_name)) {
            foreach ($this->_post as $k => $v) {
                if (stripos($k, "sdate") === false || stripos($k, "edate") === false || stripos($k, "stime") === false || stripos($k, "etime") === false) {
                    if ($this->is_value($v)) {
                        $v = $this->value_format($v, $type);

                        if ($type == "like") {
                            $this->where("trim(lower($k)) like '%" . trim(strtolower($v)) . "%'");
                        } else {
                            $this->where("$k='$v'");
                        }
                    }
                }
            }

            return $this;
        } else if (is_array($input_key_name)) {
            foreach ($input_key_name as $k => $v) {
                if (stripos($v, "sdate") === false || stripos($v, "edate") === false || stripos($v, "stime") === false || stripos($v, "etime") === false) {
                    if ($this->is_value($v)) {
                        $value = $this->value_format($value, $type);

                        if ($type == "like") {
                            $this->where("trim(lower($v)) like '%" . trim(strtolower($value)) . "%'");
                        } else {
                            $this->where("$v='$value'");
                        }
                    }
                }
            }

            return $this;
        }

        $value = $this->inputs($input_key_name, $type);

        if ($this->is_value($value)) {
            $this->where("$input_key_name='$value'");
        }

        return $this;
    }

    private function _input_value($value, $type = "")
    {
        // if ($this->is_value($value)) {
        //     switch ($type) {
        //         case "crypt":
        //             $value = $this->get_id($value);
        //             break;

        //         case "date":
        //             $value = $this->autodate($value);
        //             break;
        //     }
        // }

        return $value;
    }
}
