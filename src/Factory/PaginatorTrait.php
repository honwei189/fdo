<?php
/*
 * @creator           : Gordon Lim <honwei189@gmail.com>
 * @created           :
 * @last modified     : 21/08/2020 20:47:20
 * @last modified by  : Gordon Lim <honwei189@gmail.com>
 */

namespace honwei189\FDO\Factory;

/**
 *
 * Page separator
 *
 * Example usage :
 *
 * $find = $app->FDO()->ml_tor_list()->limit(20)->order_by("id", "desc")->where("id > 2")->limit(100);
 * print_r($find->find("id, m_name"));
 * echo $find->pagination();
 *
 *
 * @package     FDO
 * @subpackage
 * @author      Gordon Lim <honwei189@gmail.com>
 * @link        https://github.com/honwei189/fdo/
 * @version     "1.0.5" Remove "SQL_CALC_FOUND_ROWS" and change method because of it become deprecated function in mySQL 8 and will be removed in the future
 * @since       "1.0.5" Remove "SQL_CALC_FOUND_ROWS" and change method because of it become deprecated function in mySQL 8 and will be removed in the future
 */
trait PaginatorTrait
{
    private $url_args = [];

    /**
     * @access private
     * @internal
     */
    public function __construct()
    {
    }

    /**
     * To generate pagination, pass rows of data per page, and also pass an array of arguments
     * to generate URL
     *
     * This is alternative function to use the function limit() and pagination(), reduce calling two function
     * to generate pagination.
     *
     * @param int $nums_data The number of data to be displayed per page.
     * @param array $url_args This is an array of arguments that you want to pass to the navigator to generate URL.
     * @param bool $copy_uri If true, it will copy the current URL parameters and append to $url_args.
     *
     * @return An array with two elements.
     */
    public function paging($nums_data = 50, $url_args = [], $copy_uri = false)
    {
        $this->url_args = &$url_args;

        if ($copy_uri) {
            if (count($_GET) > 0) {
                foreach ($_GET as $key => $value) {
                    // pre($key . " - " . $value . " : " . (isset($this->url_args[$key])) );
                    if ($key != "p_id" && !isset($this->url_args[$key])) {
                        $this->url_args[$key] = $value;
                    }
                }
            }
        }

        // pre($this->Limit($sql, (int) $nums_data));
        // exit;

        return [
            'data'      => $this->limit((int) $nums_data)->findAll(),
            'navigator' => $this->paginator(null, $this->url_args),
        ];
    }

    /**
     *
     * Page separator
     *
     * Example usage :
     *
     * $find = $app->FDO()->ml_tor_list()->limit(20)->order_by("id", "desc")->where("id > 2")->limit(100);
     * print_r($find->find("id, m_name"));
     * echo $find->pagination();
     *
     * @param integer $nums_data The total number of data.
     * @param array $url_args This is an array of arguments that you want to pass to the navigator to generate URL.
     * @return string
     */
    public function pagination($nums_data = null, $url_args = [])
    {
        // $col       = "";
        $total = 0;
        // $nums_data = 0;

        // if (strpos($this->sql, "Select SQL_CALC_FOUND_ROWS") !== false) {
        //     $rows_resources = $this->instance->query("SELECT FOUND_ROWS() As count");
        //     $data           = $rows_resources->fetch(PDO::FETCH_BOTH, PDO::FETCH_ORI_NEXT, 0);
        //     $total          = $data['count'];
        //     unset($data);
        //     unset($rows_resources);
        // }

        // if (is_value($this->_table_cols)) {
        //     list($col) = explode(",", $this->_table_cols);
        // } else {
        //     $col = "*";
        // }

        if (is_null($nums_data)) {
            // $sql = "select count($col) from " . $this->_table . (is_value($this->_where) ? " where " . $this->_where : "");
            $sql = $this->_sql_without_limit;
            // $re             = ["/ORDER BY.*?(?=\\)|$)/mi", '/.*(\limit\s+.*)/is'];
            // $sql            = preg_replace($re, "", $sql);
            $sql   = "select count(*), t.* from ( $sql ) as t";
            $total = $this->instance->query($sql)->fetchColumn();
            // unset($re);
        }

        $limit = explode(", ", trim(str_replace("limit", "", $this->_limit)));

        if ((int) $total > 0 && (int) $limit[1] > 0) {
            $total_page = @ceil($total / $limit[1]);
        } else {
            $total_page = 0;
        }

        if (!isset($_GET['page']) || trim($_GET['page']) == "") {
            $p_id = 1;
        } else {
            $p_id = $_GET['page'];
        }

        $web_vars = $_SERVER['REQUEST_URI'];

        if (is_string($url_args)) {
            $Web_vars = $url_args;
        } else {
            $Web_vars = "/";

            foreach ($url_args as $k => $v) {
                // pre($k . " => " . $v . " = " . $_GET[$k]);

                if (is_int($k)) {
                    if ($k == 0 && $Web_vars == "/") {
                        $Web_vars .= $v;
                    } else {
                        $Web_vars .= (stripos($Web_vars, "?") !== false) ? "&" . $v : "?" . $v;
                    }
                } else {
                    $Web_vars .= (stripos($Web_vars, "?") !== false) ? "&" . $k . "=" . $v : "?" . $k . "=" . $v;
                }

                if (isset($_GET[$k]) && trim($_GET[$k]) != trim($v)) {
                    $p_id = "1";
                }
            }
        }

        if (is_array($_POST) && count($_POST) > 0) {
            foreach ($_POST as $Key => $value) {
                if (trim($Key) != "page") {
                    $_Web_vars[] = $Key . "=" . $value;
                }
            }

            $web_vars .= ((trim($web_vars) != "") ? "&" : "/?") . @join("&", $_Web_vars);
            unset($_Web_vars);
        }

        // if ($is_URL_auto_rewrite === true && count($_GET) === 0) {
        //     if (substr($web_vars, -1) !== "/") {
        //         $web_vars .= "/";
        //     }
        // }

        if (is_null($nums_data)) {
            $nums_data = $total;
        } else if (is_object($nums_data)) {
            unset($nums_data);
            $nums_data = $total;
        }

        $web_vars = trim($web_vars);
        if ($web_vars == "/" || $web_vars == "" || Substr($web_vars, -1) == "/" || !preg_match("/[&|\?]/si", $web_vars)) {
            $URL = "?page={paging}";
        } else {
            if (preg_match("/page=/isU", $web_vars, $reg)) {
                $URL = preg_replace("/page=[0-9]+$/isU", "page={paging}", $web_vars);
            } else {
                $URL = $web_vars . "&page={paging}";
            }
        }

        // if ($is_URL_auto_rewrite === true && count($_GET) === 0) {
        //     $URL = $web_vars . $URL;
        // }

        if ($p_id <= 10) {
            $init = 1;
        } else {
            $init = preg_replace("/[0-9]{1}$/", "1", $p_id * 1);
        }

        if ($nums_data >= ($p_id * 10)) {
            $numdata  = $init + 9;
            $LAST_ROW = false;

            if ($total_page >= $init && $total_page <= $nums_data) {
                $LAST_ROW = true;
                $numdata  = $total_page;
            }
        } else {
            $numdata  = $total_page;
            $LAST_ROW = true;
        }

        if ($p_id <= 10) {
            if ($total_page >= 10) {
                $_numPage = 10;
            } else {
                $_numPage = $total_page;
            }
        } else {
            $_numPage = (substr($p_id, 0, strlen($p_id) - 1) + 1) * 10;

            if ($_numPage >= $total_page) {
                $_numPage = $total_page;
            }
        }

        $paging = array();
        for ($i = $init; $i <= $_numPage; $i++) {
            $paging[] = $i;
        }

        if ($total_page > 10) {
            if ($paging[count($paging) - 1] != $total_page) {
                $paging[] = "...";
                $paging[] = $total_page;
            }
        }

        if (is_array($paging)) {
            $ret = "<ul class=\"pagination\">\n";
            if ($p_id > 10) {
                $ret .= "<li class=\"paginate_button page-item previous\"><a href=\"" . str_replace("{paging}", 1, $URL) . "\"><i class='fa fa-arrow-left icon-xs icon-orange icon-secondary'></i></a></li>\n";
            }

            foreach ($paging as $value) {
                // if ($value == "...") {
                //     $ret .= "    <li class=\"paginate_button page-item" . (($value == $p_id) ? " active" : "") . "\">$value</li>\n";
                // } else {
                $ret .= "    <li class=\"paginate_button page-item" . (($value == $p_id) ? " active" : "") . "\"><a class=\"page-link\" href=\"" . str_replace("{paging}", $value, $URL) . "\">$value</a></li>\n";
                // }
            }

            if ($total_page > $_numPage) {
                $ret .= "    <li class=\"paginate_button page-item next\"><a class=\"page-link\" href=\"" . str_replace("{paging}", ($init + 10), $URL) . "\"><i class='fa fa-arrow-right icon-xs icon-orange icon-secondary'></i></a></li>\n";
            }

            $ret .= "\n  </ul>";
        }

        if ($total_page > 0 && $_numPage > 1) {
            return $ret;
        }
    }
}
