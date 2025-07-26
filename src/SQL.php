<?php
/*
 * Created       : 2019-05-06 09:54:01 pm
 * Author        : Gordon Lim <honwei189@gmail.com>
 * Last Modified : 2020-11-01 01:51:01 pm
 * Modified By   : Gordon Lim
 * ---------
 * Changelog
 *
 * Date & time           By                    Version   Comments
 * -------------------   -------------------   -------   ---------------------------------------------------------
 * 2020-11-01 01:45 pm   Gordon Lim            1.0.1     Added new function -- get_sql().  This is to get SQL instead of send SQL to database
 *
 */

namespace honwei189\FDO;

use honwei189\Flayer\Core as flayer;

/**
 *
 * Data Operate library
 *
 *
 * @package     FDO
 * @subpackage
 * @author      Gordon Lim <honwei189@gmail.com>
 * @link        https://github.com/honwei189/fdo/
 * @version     "1.0.1" Add new function -- get_sql().  This is to get SQL instead of send SQL to database
 * @since       "1.0.0"
 */
class SQL
{
    public $action_description = null;
    public $action_type        = null;
    public $disable_laravel    = true;
    public $error              = null;
    public $fetch_mode         = null;
    public $instance           = null;
    public $is_error           = false;
    public $username           = null;
    public $user_id            = null;
    public $user_only          = false;
    public $parent             = "";
    public $_id                = 0;
    public $_passthrough       = false;
    public $_verify_sql        = false;
    private $base_path         = "";
    private $http              = null;
    private $_count_by         = "";
    private $_debug_print      = false;
    private $_driver_options   = [];
    private $_enable_logger    = null;
    private $_query_log        = null;
    private $_get_sql          = false;
    private $_off_print_format = false;
    private $_is_api           = false;
    private $_is_cli           = false;
    private $_set_encrypt_data = false;
    private $_set_encrypt_id   = false;
    private $_set_sql_to_sub   = false;
    private $_soft_update      = false;
    private $_show_sql         = false;
    private $_user             = null;
    private $_user_id          = null;
    protected $is_laravel      = false;

    // protected $require = ["helper", "crud"];
    // protected $require = ["honwei189\\data"];

    // use query {
    //     query::__construct as private __queryConstruct;
    // }

    use
    Factory\FormTrait,
    Factory\OperateTrait,
    Factory\PaginatorTrait,
    Factory\QueryTrait;

    /**
     * @access private
     * @internal
     */
    public function __construct()
    {
        if (php_sapi_name() == "cli") {
            $this->_is_api   = true;
            $this->_is_cli   = true;
            $this->base_path = realpath(__DIR__ . (isset($_SERVER['SHELL']) ? "/../../../../../" : "../../../../../../"));
        } else {
            $this->base_path = substr_replace($_SERVER['DOCUMENT_ROOT'], "", strrpos($_SERVER['DOCUMENT_ROOT'], "public"));
        }

        if (empty($this->instance)) {

            // check is laravel
            if (class_exists("Illuminate\Database\Eloquent\Model") && class_exists("Carbon\Laravel\ServiceProvider") && class_exists("DB")) {
                $this->is_laravel = true;
                $this->set_instance(("\DB")::connection('mysql')->getPdo());
                $this->instance->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            } else {
                if (file_exists($this->base_path . "/.env")) {
                    $options = [
                        "driver"   => $this->env("DB_CONNECTION"),
                        "host"     => $this->env("DB_HOST"),
                        "port"     => $this->env("DB_PORT"),
                        "database" => $this->env("DB_DATABASE"),
                        "username" => $this->env("DB_USERNAME"),
                        "password" => $this->env("DB_PASSWORD"),
                    ];

                    $this->debug($this->env("APP_DEBUG"));
                    $this->connect($options);
                }
            }
        }

        $this->version();
        $this->fetch_mode();
        $this->_user    = \honwei189\Flayer\Data::get("user");
        $this->_user_id = \honwei189\Flayer\Data::get("user_id");
    }

    /**
     * Dynamically generate new object.  $method_name = YOUR_TABLE_NAME
     *
     * You can write any data into any table without from $this->set_table();
     *
     * e.g: $this->users()->name = "AAA";
     *
     * This is means that DB table = users, and write data ("AAA") into table = "users" and the table column = "name"
     *
     * ======================
     *
     * You also can use following function :
     *
     *
     *
     * findBy
     *
     * You can find any data from $this->set_table()
     *
     * Return as string.
     *
     * Example: findBy{YOUR_TABLE_COLUMN_NAME}
     *
     * Usage:
     *
     * $this->findByuserid("admin");
     *
     * or;
     *
     * $this->findByuserid("admin", "name, userid, email");
     *
     * findBy = Predefined name
     * userid = Your table column
     *
     * Output: select userid from $this->set_table() where userid = 'admin'
     * Output: select name, userid, email from $this->set_table() where userid = 'admin'
     *
     *
     *
     * ======================
     *
     * find_exists_
     *
     *
     * Check against data table from $this->set_table(), is the data exist
     *
     * Return as boolean
     *
     * Example: find_exists_{YOUR_TABLE_COLUMN_NAME}
     *
     * Usage:
     *
     * $this->find_exists_userid("admin");
     *
     *
     * find_exists_ = Predefined name
     * userid = Your table column
     *
     * Output: select userid from $this->set_table() where userid = 'admin'
     *
     *
     *
     * ======================
     *
     *
     * is_exists_
     *
     *
     * Similar to "find_exists".  Check against data table from function name, is the data exist
     *
     * Return as boolean
     *
     * Example: is_exists_{YOUR_ANY_TABLE_NAME}->{YOUR_TABLE_COLUMN_NAME}()
     *
     * Usage:
     *
     * $this->is_exists_any_table_name()->userid("admin");
     *
     * or;
     *
     * $this->is_exists_any_table_name("status='A'")->userid("admin");
     *
     * or;
     *
     * return $this->users()->like("home_url", $_SERVER['HTTP_HOST'])->is_exist();
     *
     *
     * is_exists_ = Predefined name
     * any_table_name = Your table name
     * userid = Your table column name
     *
     * Output: select userid from any_table_name where userid = 'admin'
     * Output: select userid from any_table_name where status = 'A' and userid = 'admin'
     *
     *
     * @param mixed $method_name
     * @param mixed $arguments
     * @return mixed
     */
    public function __call($method_name, $arguments)
    {
        // try{
        //     echo $method_name."<br>";
        //     $ReflectionMethod =  new \ReflectionMethod($this, $method_name);

        //     $params = $ReflectionMethod->getParameters();

        //     $paramNames = array_map(function( $item ){
        //         return $item->getName();
        //     }, $params);

        //     pre($paramNames);
        // }catch(Exception $e){

        // }

        if (stripos($method_name, "findby") !== false) {
            $name = str_ireplace("findby", "", $method_name);

            if (isset($arguments[0]) && is_value($arguments[0])) {
                $_ = $name;

                if (count($arguments) > 1) {
                    $_ = $arguments;
                    // $_[0] = $name;
                    array_shift($_);
                    $_ = join(", ", $_);
                }
                return $this->findBy($name, $arguments[0], $_);
            } else {
                return true;
            }
        }

        if (stripos($method_name, "findallby") !== false) {
            $name = str_ireplace("findallby", "", $method_name);

            if (isset($arguments[0]) && is_value($arguments[0])) {
                $_ = $name;

                if (count($arguments) > 1) {
                    $_ = $arguments;
                    // $_[0] = $name;
                    array_shift($_);
                    $_ = join(", ", $_);
                }
                return $this->findAllBy($name, $arguments[0], $_);
            } else {
                return true;
            }
        }

        if (stripos($method_name, "find_exists_") !== false) {
            $name = str_replace("find_exists_", "", strtolower($method_name));

            if (isset($arguments[0]) && is_value($arguments[0])) {
                $find_check = $this->_db->get($this->_table, $name, $arguments[0], $name, null);
                // return (is_value($this->_db->get($this->_table, $name, "'" . $arguments[0] . "'", $name, null)) ? true : false);
                return (is_value($find_check) ? true : (is_array($find_check) ? true : false));
            } else {
                return false;
            }
        }

        if (stripos($method_name, "is_exists_") !== false) {
            $instance = clone $this;
            $instance->clear();
            $instance->set_table(str_replace("is_exists_", "", $method_name));
            $instance->_is_exists = true;

            // // $this->_is_exists[] = str_replace("is_exists_", "", $method_name);

            // $rc       = new \ReflectionClass(__class__);
            // $instance = $rc->newInstance($this->_db);
            // $instance->set_table(str_replace("is_exists_", "", $method_name));
            // $instance->_is_exists = true;
            // // $this->_is_exists = $instance;

            // // $this->_is_exists = $method_name;
            // // echo debug_backtrace()[1]['function'];

            if (isset($arguments[0]) && is_value($arguments[0])) {
                $instance->where($arguments);
            }

            return $instance;
        }

        if ($this->_is_exists) {
            if (isset($arguments[0]) && is_value($arguments[0])) {
                if (is_value($this->_where)) {
                    $this->cols($method_name);
                    $this->where("$method_name = '" . $arguments[0] . "'");
                    return (is_value($this->get($this->_table, $method_name)) ? true : false);
                } else {
                    return (is_value($this->get($this->_table, $method_name, $arguments[0], $method_name, null)) ? true : false);
                }
            }
        }

        if (stripos($method_name, "is_exist") !== false) {
            return (is_value($this->get()) ? true : false);
        }

        $instance = clone $this;
        $instance->clear();
        $instance->set_table($method_name);
        // $instance->fetch_mode($this->fetch_mode->mode, (is_string($this->fetch_mode->class) && strpos($this->fetch_mode->class, "fdoData") ? "fdoData" : $this->fetch_mode->class));
        $instance->fetch_mode = $this->fetch_mode;
        return $instance;

        // if (!isset($this->_method[$method_name])) {
        //     // $rc                                = new \ReflectionClass($this);
        //     // $instance                          = $rc->newInstance($this->instance);
        //     // $instance->_table_alias            = "";
        //     // $instance->_table_cols             = "";
        //     // $instance->_table_cols_nums        = 0;
        //     // $instance->_table_join             = null;
        //     // $instance->_table_joins            = null;
        //     // $instance->_table_joins_table      = null;
        //     // $instance->_table_left_joins       = null;
        //     // $instance->_table_left_joins_table = null;
        //     // $instance->_union                  = null;
        //     // $instance->_union_tables           = null;
        //     // $instance->_vars                   = null;
        //     // $instance->_raws                   = null;
        //     // $instance->_where                  = "";
        //     // $instance->_limit                  = "";
        //     // $instance->_count_by               = "";
        //     // $instance->_count_group            = false;
        //     // $instance->_group_by               = "";
        //     // $instance->_order_by               = "";
        //     // $instance->_sum_by                 = "";
        //     // $instance->debug($this->_debug_print);
        //     // $instance->off_print_format($this->_off_print_format);
        //     // $instance->set_table($method_name);
        //     // $instance->soft_update($this->_soft_update);

        //     // $this->_method[$method_name] = $instance;
        //     // unset($rc);

        //     $instance = clone $this;
        //     $instance->set_table($method_name);
        //     return $instance;
        // }

        // return $this->_method[$method_name];
    }

    public function __get($arg)
    {
        if (isset($this->_vars[$arg]) && is_value($this->_vars[$arg])) {
            return $this->_vars[$arg];
        }
    }

    public function __isset($name)
    {
        return (isset($this->_vars[$name]) ? true : false);
    }

    public function __unset($name)
    {
        if (isset($this->_vars[$name])) {
            unset($this->_vars[$name]);
        }

        if (isset($this->_method[$name])) {
            unset($this->_method[$name]);
        }
    }

    public function __set($name, $val)
    {
        // unset($this->{"action"});
        unset($this->{"api_Key"});
        unset($this->{"token"});
        unset($this->{"key"});
        unset($this->{"file"});
        unset($this->{"id"});

        if ($name == "_set_encrypt_id") {
            return;
        }

        switch ($name) {
            // case "action":
            case "_token":
            case "_method":
            case "fillable":
                break;

            default:
                $this->_vars[$name] = $val;
                break;
        }
    }

    /**
     * Define operate action description, for audit purpose.  E.g:  Created new profile for XXXX / Received alert from XXX
     *
     * @param string $dscpt
     * @return db_helper
     */
    public function action_description($dscpt)
    {
        $this->action_description = $dscpt;

        return $this;
    }

    /**
     * Define current operation (save, store, update) action for audit log purpose.  E.g:  Receive, Update, Create and etc...
     *
     * @param string $action
     * @return db_helper
     */
    public function action_type($action)
    {
        $this->action_type = $action;

        return $this;
    }

    /**
     * Implode an array with the key and value pair giving
     * a glue, a separator between pairs and the array
     * to implode.
     * @param string $glue The glue between key and value
     * @param string $separator Separator between pairs
     * @param array $array The array to implode
     * @return string The imploded array
     */
    public function array_implode($glue, $separator, $array)
    {
        if (!is_array($array)) {
            return $array;
        }

        $string = array();
        foreach ($array as $key => $val) {
            if (is_array($val)) {

                $val = $this->array_implode($glue, $separator, $val);
            }

            $string[] = "{$key}{$glue}{$val}";

        }
        return implode($separator, $string);
    }

    /**
     * Activate transaction
     */
    public function begin()
    {
        $this->begin_trx();
        $this->_trx = true;
    }

    public function begin_trx()
    {
        if ($this->instance) {
            if (!$this->instance->inTransaction()) {
                $this->instance->beginTransaction();
            }
        }
    }

    /**
     * PDO connection
     *
     * @param PDO_INSTANCE_OBJECT|array $options PDO instance connection object or configuration array
     * @return PDO_INSTANCE_OBJECT
     */
    public function connect($options = null)
    {
        if (empty($this->instance) && is_null($options)) {
            if (file_exists($this->base_path . "/.env")) {
                $options = [
                    "driver"   => $this->env("DB_CONNECTION"),
                    "host"     => $this->env("DB_HOST"),
                    "port"     => $this->env("DB_PORT"),
                    "database" => $this->env("DB_DATABASE"),
                    "username" => $this->env("DB_USERNAME"),
                    "password" => $this->env("DB_PASSWORD"),
                ];

                $this->debug($this->env("APP_DEBUG"));
                $this->enable_crud_log((bool) $this->env("DB_LOGGER_CRUD"));
                $this->enable_query_log((bool) $this->env("DB_LOGGER_QUERY"));
            }
        }

        if (is_resource($options) || is_object($options)) {
            $this->instance = $options;
        } else {
            if (isset($options['audit'])) {
                $this->audit = true;
            }

            if (empty($this->instance)) {
                try {
                    switch ($options['driver']) {
                        case "redis":
                            break;

                        default:
                            if (isset($options['read']) && isset($options['write'])) {
                                $this->instance['read'] = new \PDO(
                                    $options['read']['driver'] . ':host=' . $options['read']['host'] . ';dbname=' . $options['read']['database'] . ';port=' . $options['read']['port'],
                                    $options['read']['username'],
                                    $options['read']['password'],
                                    [
                                        \PDO::ATTR_PERSISTENT         => true,
                                        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                                        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET CHARACTER SET utf8",
                                    ]
                                );
                                $this->instance['read']->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                                $this->instance['read']->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

                                $this->instance['write'] = new \PDO(
                                    $options['write']['driver'] . ':host=' . $options['write']['host'] . ';dbname=' . $options['write']['database'] . ';port=' . $options['write']['port'],
                                    $options['write']['username'],
                                    $options['write']['password'],
                                    [
                                        \PDO::ATTR_PERSISTENT => true,
                                        // \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                                        // \PDO::MYSQL_ATTR_INIT_COMMAND => "SET CHARACTER SET utf8",
                                    ]
                                );
                                $this->instance['write']->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                                $this->instance['write']->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
                            } else {
                                try {
                                    $this->instance = new \PDO(
                                        $options['driver'] . ':host=' . $options['host'] . ';dbname=' . $options['database'] . ';port=' . $options['port'],
                                        $options['username'],
                                        $options['password'],
                                        [
                                            \PDO::ATTR_PERSISTENT => true,
                                            // \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                                            // \PDO::MYSQL_ATTR_INIT_COMMAND => "SET CHARACTER SET utf8",
                                        ]
                                    );

                                    $this->instance->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                                    $this->instance->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
                                } catch (\PDOException $e) {
                                    echo $e->getMessage();
                                }

                            }

                            break;
                    }
                } catch (\PDOException $e) {
                    echo $e->getMessage();
                }
            }
        }

        // flayer::fdo($this);
        // $this->__queryConstruct($this, $this->instance);
        // $this->__queryConstruct($this);
        $options = null;
        return $this->instance;
    }

    /**
     * Clear FDO cache
     *
     * @param boolean $clear_all
     * @return FDO
     */
    public function clear($clear_all = true)
    {
        $this->action_description      = null;
        $this->action_type             = null;
        $this->derived                 = false;
        $this->derived_sql             = "";
        $this->_is_exists              = null;
        $this->_where                  = "";
        $this->_count_by               = "";
        $this->_count_group            = false;
        $this->_order_by               = "";
        $this->_group_by               = "";
        $this->_limit                  = "";
        $this->_max_by                 = "";
        $this->_mysql_operator_symbol  = [];
        $this->_sql                    = "";
        $this->_table_cols             = "";
        $this->_table_cols_nums        = 0;
        $this->_table_join             = null;
        $this->_table_joins            = null;
        $this->_table_joins_table      = null;
        $this->_table_left_joins       = null;
        $this->_table_left_joins_table = null;
        $this->_vars                   = [];
        $this->username                = null;
        $this->user_id                 = null;
        $this->user_only               = null;
        $this->parent                  = null;
        $this->fillable                = null;
        $this->is_nofillable           = null;
        $this->use_yield               = false;

        if ($clear_all) {
            $this->_table_alias      = null;
            $this->_table_alias_temp = null;
        }

        $this->fetch_mode();

        return $this;
    }

    /**
     * Close PDO and mySQL connection
     *
     */
    public function close()
    {
        $this->instance = null;
    }

    /**
     * Commit transaction and write all data into DB
     */
// Replace the existing commit method in SQL.php
    /**
     * Commits the current transaction.
     * Retries on "lock wait timeout" errors.
     */
    public function commit()
    {
        if ($this->_soft_update) {
            $this->_trx = false;
            return;
        }

        $this->_trx     = false;
        $retry_interval = 2; // seconds
        $max_retries    = 5;
        $retry_count    = 0;

        while (true) {
            try {
                if ($this->instance->inTransaction()) {
                    $this->instance->commit();
                }
                break; // Exit loop on success
            } catch (\PDOException $e) {
                // Check for lock wait timeout error code (MySQL: 1205)
                if ($e->getCode() == '40001' || $e->getCode() == 'HY000' && strpos($e->getMessage(), 'Lock wait timeout') !== false) {
                    if ($retry_count >= $max_retries) {
                        throw $e; // Max retries reached
                    }
                    // Wait and retry
                    sleep($retry_interval);
                    $retry_count++;
                } else {
                    // Rethrow other exceptions immediately
                    throw $e;
                }
            }
        }
    }

    public function commit_trx()
    {
        if ($this->instance) {
            if ($this->instance->inTransaction()) {
                $this->instance->commit();
            }
        }
    }

    /**
     * Print SQL without operate DB
     *
     * @param boolean $debug
     */
    public function debug($debug = true)
    {
        $this->_debug_print = $debug;

        return $this;
    }

    public function debug_print_backtrace($traces_to_ignore = 1)
    {
        $traces = debug_backtrace();
        $ret    = array();
        foreach ($traces as $i => $call) {
            if ($i < $traces_to_ignore) {
                continue;
            }

            $object = [];

            if (isset($call['class'])) {
                $object = $call['class'] . $call['type'];
                if (isset($call['args']) && is_array($call['args'])) {
                    foreach ($call['args'] as &$arg) {
                        $this->debug_get_arg($arg);
                    }
                }
            }

            if (is_array($object)) {
                $_ = $object;
                unset($object);
                ob_start();
                print_r($_);
                $object = ob_get_contents();
                ob_end_clean();
                unset($_);
            }

            if (is_array($call['function'])) {
                $_ = $call['function'];
                unset($call['function']);
                ob_start();
                print_r($_);
                $call['function'] = ob_get_contents();
                ob_end_clean();
                unset($_);
            }

            if (is_array($call['file'])) {
                $_ = $call['file'];
                unset($call['file']);
                ob_start();
                print_r($_);
                $call['file'] = ob_get_contents();
                ob_end_clean();
                unset($_);
            }

            if (is_array($call['line'])) {
                $_ = $call['line'];
                unset($call['line']);
                ob_start();
                print_r($_);
                $call['line'] = ob_get_contents();
                ob_end_clean();
                unset($_);
            }

            $ret[] = '#' . str_pad($i - $traces_to_ignore, 3, ' ')
                . (isset($object) && is_array($object) && count($object) > 0 ? $this->array_implode(", ", "\n\t\t", $object) : $object)
                . (isset($call['function']) && is_array($call['function']) && count($call['function']) > 0 ? $this->array_implode(", ", "\n\t\t", $call['function']) : $call['function'])
                . (isset($call['args']) && is_array($call['args']) ? '(' . $this->array_implode(", ", "\n\t\t", $call['args']) . ')' : (isset($call['args']) && is_value($call['args']) ? "{$call['args']}" : ""))
                . ' called at [' . $call['file'] . ':' . $call['line'] . ']';
        }

        return implode("\n", $ret);
    }

    public function debug_get_arg(&$arg)
    {
        if (is_object($arg)) {
            $arr  = (array) $arg;
            $args = array();
            foreach ($arr as $key => $value) {
                if (strpos($key, chr(0)) !== false) {
                    $key = ''; // Private variable found
                }

                // $args[] = '[' . $key . '] => ' . $this->get_arg($value);
                ob_start();
                print_r($value);
                $v = ob_get_contents();
                ob_end_clean();

                $args[] = '[' . $key . '] => ' . $v;
            }
            $arg = get_class($arg) . ' Object (' . implode(',', $args) . ')';
        }
    }

    /**
     * Disable certain function using laravel eloquent's coding language while FDO been installed into Laravel project
     *
     * @param bool $bool
     * @return FDO
     */
    public function disable_laravel(bool $bool = true)
    {
        $this->disable_laravel = $bool;

        return $this;
    }

    public function error($rs, $additional_string = "")
    {
        if (is_object($rs)) {
            if (get_class($rs) == "PDOException") {
                $this->is_error = true;
                if (\honwei189\Flayer\config::get("flayer", "debug")) {
                    $msg = "";

                    if (is_object($rs)) {
                        $msg = $rs->getMessage();
                    } else if (is_array($rs)) {
                        $msg = end($rs);
                    } else if (is_string($rs)) {
                        $msg = $rs;
                    }

                    $this->throwout($msg, $additional_string);
                }
            }
        } else {
            if (\honwei189\Flayer\config::get("flayer", "debug") && is_object($rs)) {
                $this->error = $rs->errorInfo();

                if (count($this->error) > 1) {
                    if (!empty($this->error[2])) {
                        $this->is_error = true;
                        echo $this->error[2];
                        exit;
                    }
                }
            }
        }
    }

    /**
     * Enable Create/Update/Delete audit log, write transaction into table
     *
     * @param bool $enable
     */
    public function enable_crud_log($enable = true)
    {
        $this->_enable_logger = $enable;
    }

    /**
     * Enable SELECT statement query, write transaction into table
     *
     * @param bool $enable
     */
    public function enable_query_log($bool = true)
    {
        $this->_query_log = $bool;

        return $this;
    }

    public function env($key, $default = null)
    {
        if (!function_exists('env')) {
            $value = getenv($key);
            if ($value === false) {
                return $default;
            }
            switch (strtolower($value)) {
                case 'true':
                case '(true)':
                    return true;
                case 'false':
                case '(false)':
                    return false;
                case 'empty':
                case '(empty)':
                    return '';
                case 'null':
                case '(null)':
                    return;
            }
            // if ((substr($value, 0, strlen($value)) === '"') && (substr($value, -strlen($value)) === '"')) {
            //     return substr($value, 1, -1);
            // }

            if ((substr($value, 0) === '"') && (substr($value, -1) === '"')) {
                return substr($value, 1, -1);
            }

            return $value;
        } else {
            return "env"($key, $default);
        }
    }

    public function free($resources)
    {
        if (!empty($this->instance)) {
            if (Is_object($resources)) {
                $resources->closeCursor();
            }

            unset($resources);
        }
    }

    /**
     * Declare database table for actions ( create, update, delete, find and etc... )
     * @param string $table_name Table name
     * @param string $alias_name Table alias name
     * @return FDO
     */
    public function from($table_name, $alias_name = null)
    {
        return $this->set_table($table_name, $alias_name);
    }

    /**
     * Get DB inserted ID ( after called save(), store() ) or Convert encrypted id to integer
     *
     * @param string $string Encrypted ID.  If passing as null, get DB inserted ID
     * @return int
     */
    public function get_id(?string $string = null): int
    {
        if (is_null($string)) {
            // return (int) $this->_id;
            return (int) $this->last_id();
        }

        return (int) flayer::Crypto()->decrypt($string);
    }

    /**
     * Get PDO instance
     *
     * @return PDO_INSTANCE_OBJECT
     */
    public function get_instance()
    {
        return $this->instance;
    }

    /**
     * Get SQL instead of send query to database
     *
     *
     * @param boolean $bool
     * @return FDO
     */
    public function get_sql($bool = true)
    {
        $this->_get_sql = $bool;

        return $this;
    }

    public function get_user_id()
    {
        if ($this->is_laravel) {
            return "Auth"::id();
        } else {
            return $this->_user_id;
        }
    }

    /**
     * Get inserted ID from DB by after called save(), store()
     * @return int
     */
    public function id(): int
    {
        // return (int) $this->_id;
        return $this->last_id();
    }

    /**
     * Is DB connected?
     *
     * @return boolean
     */
    public function is_connected()
    {
        if (!empty($this->instance)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Is activated count table field?
     *
     * @return boolean
     */
    public function is_count()
    {
        return ($this->is_value($this->_count_by) ? true : false);
    }

    /**
     * Is on debug mode?
     *
     * @return boolean
     */
    public function is_debug()
    {
        return $this->_debug_print;
    }

    /**
     * Check is enabled logger for CRUD
     *
     * @return bool
     */
    public function is_enabled_crud_log()
    {
        if (!is_null($this->_enable_logger)) {
            return (bool) $this->_enable_logger;
        }

        return (bool) $this->env("DB_LOGGER_CRUD");
    }

    /**
     * Check is enabled logger for query statement
     *
     * @return bool
     */
    public function is_enabled_query_log()
    {
        if (!is_null($this->_query_log)) {
            return (bool) $this->_query_log;
        }

        return (bool) $this->env("DB_LOGGER_QUERY");
    }

    /**
     * Check is under Laravel platform
     *
     * @return bool
     */
    public function is_laravel(): bool
    {
        return (bool) $this->is_laravel;
    }

    /**
     * @param boolean $bool
     */
    public function is_set_encrypt_id()
    {
        return $this->_set_encrypt_id;
    }

    /**
     * Is on soft update mode?
     *
     * @param boolean $bool
     */
    public function is_soft_update()
    {
        return $this->_soft_update;
    }

    /**
     * Checks if a transaction is currently active
     *
     * @return bool
     */
    public function is_transaction()
    {
        return (bool) $this->instance->inTransaction();
    }

    /**
     * Get last insert ID from DB.  Alternative function is get_id(), id()
     *
     * @return int
     */
    public function last_id()
    {
        if (!empty($this->instance)) {
            $this->_id = $this->instance->lastInsertId();
            return (int) $this->_id;
        }
    }

    /**
     * Enable logger for find(), get() to trace current SELECT SQL
     *
     * @param bool $bool
     * @return fdo
     */
    public function log($bool = true)
    {
        $this->_logger = $bool;
        return $this;
    }

    /**
     * Turn-off debug print beautifier output
     *
     * @param boolean $bool
     */
    public function off_print_format($bool = true)
    {
        $this->_off_print_format = (bool) $bool;
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

    /**
     * Print out messages / SQL with pretty print (styles format)
     *
     * @param mixed $string Main messages / SQL to print out
     * @param mixed $additional_string Additional messages to print out
     */
    public function print_sql_format($string, $additional_string = null)
    {
        if ($this->_off_print_format) {
            if (($this->_is_api || $this->_is_cli) || $this->_off_print_format) {
                echo \sqlformatter::format($string, false) . PHP_EOL . PHP_EOL . $additional_string . PHP_EOL;
            } else {
                echo $string . "<br>" . $additional_string . "<br>";
            }
        } else {
            if ($this->_is_api) {
                echo \sqlformatter::format($string, false);
                // echo $string . PHP_EOL;
                echo str_replace("<br>", PHP_EOL, $additional_string) . PHP_EOL;
            } else {
                $this->throwout_html(\sqlformatter::format($string), $additional_string);
            }
        }
    }

    /**
     * Restore data from transaction
     */
    public function rollback()
    {
        if (!$this->_soft_update) {
            $this->rollBack_trx();
        }
    }

    public function rollback_trx()
    {
        if ($this->instance) {
            if ($this->instance->inTransaction()) {
                $this->instance->rollBack();
            }
        }
    }

    /**
     * Encrypt all data fetch from DB
     *
     * @param boolean $bool
     */
    public function set_encrypt_data($bool = true)
    {
        $this->_set_encrypt_data = $bool;

        return $this;
    }

    /**
     * Encrypt the ID.  This is applicable for fetchAll().  When return id, use encrypted id instead of number to protect real id
     *
     * @param boolean $bool
     */
    public function set_encrypt_id($bool = true)
    {
        $this->_set_encrypt_id = $bool;

        return $this;
    }

    /**
     * Set what data to stores in database
     *
     * Applicable to add(), create(), save(), store(), update() only
     *
     * @param array|string $name
     * @param array|string $value
     * @return FDO
     */
    public function set_fill_data($name, $value)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->__set($v, (is_array($value) ? $value[$k] : $value));
            }
        } else {
            $this->__set($name, (is_array($value) ? $value[0] : $value));
        }

        return $this;
    }

    /**
     * Set PDO instance
     *
     * @param PDO_INSTANCE_OBJECT $pdo_object
     * @return FDO
     */
    public function set_instance($pdo_object)
    {
        $this->instance = $pdo_object;
        return $this->instance;
    }

    /**
     * Declare database table for actions ( create, update, delete, find and etc... )
     * @param string $table Table name
     * @param string $alias Table alias name
     * @return FDO
     */
    public function set_table($table, $alias = null)
    {
        $this->_table       = $table;
        $this->_table_alias = $alias;

        return $this;
    }

    /**
     * Set default table alias name
     * @param string $alias Alias name
     * @return FDO
     */
    public function set_table_alias($alias)
    {
        $this->_table_alias = $alias;

        return $this;
    }

    /**
     * Set temporarily default table alias name
     * @param string $alias Alias name
     * @return FDO
     */
    public function set_table_temp_alias($alias)
    {
        $this->_table_alias_temp = $this->_table_alias;
        $this->_table_alias      = $alias;

        return $this;
    }

    public function set_try_catch()
    {
        $this->instance->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Soft insert / update / delete data
     *
     * Soft updates meaning not actually insert / update / delete data from DB.
     *
     * This function is to prove that the process (insert / update / delete ) is working fine.
     *
     * @param boolean $bool
     * @return FDO
     */
    public function soft_update($bool = true)
    {
        $this->_soft_update = $bool;

        return $this;
    }

    /**
     * Print out SQL with Save / Store / Update action
     *
     *
     * @param boolean $bool
     * @return FDO
     */
    public function show_sql($bool = true)
    {
        $this->_show_sql = $bool;

        return $this;
    }

    /**
     * Declare database table for actions ( create, update, delete, find and etc... )
     * @param string $table_name Table name
     * @param string $alias_name Table alias name
     * @return FDO
     */
    public function table($table_name, $alias_name = null)
    {
        return $this->set_table($table_name, $alias_name);
    }

    /**
     * Certain function using laravel eloquent's coding language while FDO been installed into Laravel project
     *
     * @param bool $bool
     * @return FDO
     */
    public function use_laravel(bool $bool = true)
    {
        $this->disable_laravel = !$bool;

        return $this;
    }

    /**
     * Output exception message with HTML formatted
     *
     * @param mixed $string
     * @param mixed $additional_string
     */
    public function throwout($string, $additional_string = null)
    {

        if (class_exists("Illuminate\Database\Eloquent\Model") && class_exists("Carbon\Laravel\ServiceProvider")) {
            if (str($additional_string)) {
                // $html($string);
                // throw new \Exception($additional_string);

                $this->throwout_html(\sqlformatter::format($additional_string));
                throw new \Exception($string);
            } else {
                throw new \Exception($string);
            }
        } else {
            $this->throwout_html($string, $additional_string);
            exit;
        }
    }

    public function verify($bool = true)
    {
        $this->_verify_sql = $bool;
        return $this;
    }

    public function version()
    {
        $version = \honwei189\Flayer\Data::get("DB_VERSION");

        if (!str($version) && is_object($this->instance)) {
            $version = $this->instance->query('select version()')->fetchColumn();

            preg_match("/^[0-9\.]+/", $version, $match);

            $version = $match[0];
            \honwei189\Flayer\Data::set("DB_VERSION", $version);
            unset($match);
        }

        return $version;
    }

    public function write_audit_log($id, $action, $raws, $inputs)
    {
        // $id = (int)$id;

        // $schema = "set autocommit=0; CREATE TABLE if not exists `logs_" . $this->_table . "` (

        if (is_null($raws)) {
            if (is_null($this->http)) {
                $this->http = (flayer::exists("\\honwei189\\Flayer\\Http") ? flayer::get("\\honwei189\\Flayer\\Http") : flayer::bind("\\honwei189\\Flayer\\Http"));
            }

            $raws = ($this->http->type == "json" ? json_decode($this->http->_raws, true) : (is_array($_REQUEST) ? $_REQUEST : ""));
        }

        if ($this->_multi_log_table) {
            $schema = "CREATE TABLE if not exists `logs_" . $this->_table . "` (";
        } else {
            $schema = "CREATE TABLE if not exists `logs` (";
        }

        $schema .= "
                `id` INT(18) NOT NULL AUTO_INCREMENT,
                `client_id` INT(18),
                `action` VARCHAR(3),
                `action_description` VARCHAR(300),";

        if (!$this->_multi_log_table) {
            $schema .= "
                `tbl` VARCHAR(150),";
        }

        $schema .= "
                `ref_id` INT(18),
                `rel_id` INT(18),
                `inputs` TEXT,
                `form_post_raws` TEXT,
                `curr_db_data` TEXT,
                `ip` VARCHAR(20),
                `read` TINYINT(1) NULL DEFAULT '0',
                `cdt` DATETIME,
                `crby` VARCHAR(150) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX `logs_idx` (`id`, `action`, `tbl`, `ref_id`)
            )
            COLLATE='utf8_general_ci'
            ENGINE=MyISAM;";

        // unset($raws['status']);
        // unset($raws['crdate']);
        // unset($raws['cdt']);
        // unset($raws['crby']);
        // unset($raws['lupdate']);
        // unset($raws['ldt']);
        // unset($raws['lupby']);

        if (is_array($inputs)) {
            foreach ($inputs as $k => $v) {
                switch ($v) {
                    case "now()":
                    case "current_timestamp":
                        $inputs[$k] = date("Y-m-d H:i:s");
                        break;

                    case "current_date":
                        $inputs[$k] = date("Y-m-d");
                        break;
                }
            }
        }

        // $sql = "$schema replace into logs_" . $this->_table . " select null, null, '$action', $id, null, null, '" . json_encode($inputs) . "', '" . (is_array($raws) ? json_encode($raws) : "") . "', '" . Common::getIP() . "', 0, now(), " . (isset($this->userid) ? $this->userid : "'system'") . ";";
        // $sql = "insert into logs_" . $this->_table . " values (null, null, '$action', $id, null, null, '" . addslashes(json_encode($inputs)) . "', '" . (is_array($raws) ? addslashes(json_encode($raws)) : "") . "', '" . Common::getIP() . "', 0, now(), " . (isset($this->userid) ? $this->userid : "'system'") . ");";

        // $find = $this->data("SHOW TABLES LIKE 'logs_" . $this->_table . "'");

        // if(is_array($find) && count($find) == 0){
        //     $this->_db->Execute($schema);
        //     if ($this->_trx) {
        //         if (!$this->_db->Is_Success()) {
        // $this->_db->Rollback();
        //             return false;
        //         }
        //     }
        // }

        $this->action_type = (is_value($this->action_type) ? $this->action_type : $action);

        if ($this->_trx) {
            // $sql = "insert into logs_" . $this->_table . " values (null, null, '$action', $id, null, null, '" . json_encode($inputs) . "', '" . (is_array($raws) ? json_encode($raws) : "") . "', '" . Common::getIP() . "', 0, now(), " . (isset($this->_user) ? "'" . $this->_user . "'" : "'system'") . ");";

            $sql = "insert into " . ((!$this->_multi_log_table) ? "logs" : "logs_" . $this->_table) . " values (
                    null,
                    " . (isset($this->_user_company_id) && (double) $this->_user_company_id > 0 ? (double) $this->_user_company_id : "null") . ",
                    '" . $this->action_type . "',
                    " . (is_value($this->action_description) ? "'" . $this->action_description . "'" : "null") . " ,";

            if (!$this->_multi_log_table) {
                $sql .= "
                    '$this->_table',";
            }

            $sql .= "
                    $id,
                    null,
                    '" . addslashes(json_encode($inputs)) . "',
                    '" . (is_array($_REQUEST) ? (json_encode($_REQUEST)) : "") . "',
                    '" . (is_array($raws) ? (json_encode($raws)) : "") . "',
                    '" . $this->getIP() . "',
                    0,
                    now(),
                    " . (isset($this->_user) ? "'" . $this->_user . "'" : "'system'") .
                ");";

            // $this->print($sql);
            // exit;

            $this->execute($sql);

            //if ($this->_trx) {
            if ($this->is_error) {
                // $this->_db->Rollback();
                return false;
            }
            //}
        } else {
            // If not on transaction mode, auto create log table
            // $sql = "$schema replace into logs_" . $this->_table . " select null, null, '$action', $id, null, null, '" . json_encode($inputs) . "', '" . (is_array($raws) ? json_encode($raws) : "") . "', '" . Common::getIP() . "', 0, now(), " . (isset($this->_user) ? "'" . $this->_user . "'" : "'system'") . ";";
            $stmt = $this->instance->prepare($schema);
            $stmt->closeCursor();
            $stmt->execute();

            $sql = "replace into " . ((!$this->_multi_log_table) ? "logs" : "logs_" . $this->_table) . "
                select
                    null,
                    null,
                    '" . $this->action_type . "',
                    " . (is_value($this->action_description) ? "'" . $this->action_description . "'" : "null") . " ,";

            if (!$this->_multi_log_table) {
                $sql .= "
                    '$this->_table',";
            }

            $sql .= "
                    $id,
                    null,
                    '" . addslashes(json_encode($inputs)) . "',
                    '" . (is_array($_REQUEST) ? (json_encode($_REQUEST)) : "") . "',
                    '" . (is_array($raws) ? (json_encode($raws)) : "") . "',
                    '" . $this->getIP() . "',
                    0,
                    now(),
                    " . (isset($this->_user) ? "'" . $this->_user . "'" : "'system'") .
                ";";

            $stmt = $this->instance->prepare($sql);

            try {
                $stmt->closeCursor();
                $stmt->execute();
            } catch (\PDOException $e) {
                ob_start();
                print_r($e->errorInfo);
                $except = new \Exception;
                print_r($except->getTraceAsString());
                $error = ob_get_contents();
                ob_end_clean();

                $this->write_exceptional($sql, $e->getMessage(), $error);
                unset($error);
                unset($except);

                // pre($stmt->errorInfo());
                return false;
            }

            $stmt->closeCursor();
        }

        $this->action_type        = null;
        $this->action_description = null;

        unset($inputs);
        unset($schema);
        unset($stmt);
        unset($sql);

        // $sql = "insert into logs_" . $this->_table . " values (null, null, '$action', $id, null, null, '" . json_encode($inputs) . "', '" . (is_array($raws) ? json_encode($raws) : "") . "', '" . Common::getIP() . "', 0, now(), " . (isset($this->userid) ? $this->userid : "'system'") . ");";

        // // echo $sql;
        // // exit;

        // $this->_db->Execute($sql);
        // if ($this->_trx) {
        //     if (!$this->_db->Is_Success()) {
        // $this->_db->Rollback();
        //         return false;
        //     }
        // }
    }

    public function write_exceptional($sql, $error, $error_trace)
    {
        if (is_null($this->http)) {
            $this->http = (flayer::exists("\\honwei189\\Flayer\\Http") ? flayer::get("\\honwei189\\Flayer\\Http") : flayer::bind("\\honwei189\\Flayer\\Http"));
        }

        $begin = false;
        if ($this->instance->inTransaction()) {
            $this->commit_trx();
            $begin = true;
        }

        $inputs = null;

        if (isset($this->_vars) && is_array($this->_vars) && count($this->_vars) > 0) {
            foreach ($this->_vars as $k => $v) {
                switch ($v) {
                    case "now()":
                    case "current_timestamp":
                        $inputs[$k] = date("Y-m-d H:i:s");
                        break;

                    case "current_date":
                        $inputs[$k] = date("Y-m-d");
                        break;
                }
            }
        }

        $_REQUEST = filter_var_array($_REQUEST, FILTER_SANITIZE_STRING);
        // $_REQUEST = filter_var_array($_REQUEST, FILTER_SANITIZE_MAGIC_QUOTES);
        // $_REQUEST = filter_var(
        //     $_REQUEST,
        //     FILTER_CALLBACK,
        //     array("options" => array($this, "sanitize_min_clean_array"))
        // );

        // $stmt = $this->instance->prepare("SET sql_notes = 0;");
        // $stmt->execute();

        $schema = "
        CREATE TABLE if not exists `logs_exceptional` (
            `id` INT(18) NOT NULL AUTO_INCREMENT,
            `module` varchar(50),
            `action` VARCHAR(3),
            `action_dscpt` VARCHAR(300),
            `uri` varchar(800),
            `request_method` varchar(10),
            `request_header` text,
            `sql` TEXT NULL,
            `error` varchar(500) NULL,
            `trace` TEXT NULL,
            `inputs` TEXT,
            `form_post_raws` TEXT,
            `ip` varchar(50),
            `crdt` datetime,
            `crby` varchar(150),
            PRIMARY KEY (`id`)
        )
        ENGINE=MyISAM;
        ";

        // $check = $this->read_one_sql("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'logs_exceptional'");

        // // $check = $this->sql_find("SHOW TABLES LIKE '".$this->db_table."'");

        // if (is_array($check) && count($check) > 0) {
        // } else {
        //     $stmt = $this->instance->prepare($schema);
        //     $stmt->closeCursor();
        //     $stmt->execute();
        // }

        $stmt = $this->instance->prepare($schema);
        $stmt->closeCursor();
        $stmt->execute();

        // '" . trim(addslashes($this->debug_print_backtrace())) . "',

        $sql = "replace into logs_exceptional select
            null,
            '" . (isset($_SESSION['APP']) ? $_SESSION['APP'] : "general") . "',
            " . (is_value($this->action_type) ? "'" . $this->action_type . "'" : "null") . " ,
            " . (is_value($this->action_description) ? "'" . $this->action_description . "'" : "null") . " ,
            '" . $_SERVER['REQUEST_URI'] . "',
            '" . $this->http->type . "',
            '" . tostring($this->http->header) . "',
            '" . addslashes($sql) . "',
            '" . addslashes($error) . "',
            '" . addslashes($error_trace) . "',
            " . (isset($inputs) && is_array($inputs) && count($inputs) > 0 ? "'" . (json_encode($inputs)) . "'" : "null") . ",
            '" . ($this->http->type == "json" ? ($this->http->_raws) : (is_array($_REQUEST) ? (json_encode($_REQUEST)) : "")) . "',
            '" . $this->getip() . "',
            now(),
            " . (isset($this->_user) ? "'" . $this->_user . "'" : "'system'") .
            ";
        ";

        // $sql = "insert into db_exceptional (module, uri, `sql`, dscpt, `trace`, ip, crdt, crby) values ('" . (isset($_SESSION['APP']) ? $_SESSION['APP'] : "general") . "', '" . $_SERVER['REQUEST_URI'] . "', '" . addslashes($sql) . "', '" . addslashes($error) . "', '" . trim(addslashes($this->debug_print_backtrace())) . "', '" . $this->getip() . "', now(), '" . $_SESSION['userid'] . "');"

        $stmt = $this->instance->prepare($sql);
        $stmt->closeCursor();
        $stmt->execute();

        // $stmt = $this->instance->prepare("SET sql_notes = 1;");
        // $stmt->execute();

        if ($begin) {
            $this->begin_trx();
        }

        $email = \honwei189\Flayer\Config::get("flayer", "email");

        if (isset($email) && is_array($email) && count($email) > 0) {
            if ($email['send_to'] ?? false) {
                // ob_start();
                // pre($this->debug_print_backtrace());
                // $trace = ob_get_contents();
                // ob_end_clean();

                $_inputs = null;
                if (isset($inputs) && is_array($inputs)) {
                    ob_start();
                    pre($inputs);
                    $_inputs = ob_get_contents();
                    ob_end_clean();
                }

                $_request = null;
                if (isset($_REQUEST) && is_array($_REQUEST)) {
                    ob_start();
                    pre($_REQUEST);
                    $_request = ob_get_contents();
                    ob_end_clean();
                }

                $_session = null;
                if (isset($_SESSION) && is_array($_SESSION)) {
                    ob_start();
                    pre($_SESSION);
                    $_session = ob_get_contents();
                    ob_end_clean();
                }

                $contents = "
                Date & Time : " . date("Y-m-d H:i:s") . "<br>
                IP : " . $this->getip() . "<br>
                Module : " . (isset($_SESSION['APP']) ? $_SESSION['APP'] : "general") . "<br>
                Action : " . (is_value($this->action_type) ? $this->action_type : "") . "<br>
                Description : " . (is_value($this->action_description) ? $this->action_description : "") . "<br>
                Inputs : $_inputs<br>
                Requests : $_request<br>
                Session : $_session<br>
                Error : $error<br>
                SQL : $sql<br>
                Trace : <hr>
                $error_trace
                ";

                send_mail(($email['sender_name'] ?? getenv("APP_NAME") ?? "") . $email['sender_email'], $email['send_to'], $_SERVER['HTTP_HOST'] . " DB exception", $contents);

                $contents = null;
                $trace    = null;
                $_inputs  = null;
                $_request = null;
                $_session = null;

            }
        }

        $this->action_type        = null;
        $this->action_description = null;
        unset($begin);
        unset($email);
        unset($inputs);
        unset($schema);
        unset($sql);
    }

    /**
     * Before store to database / select data with selected table columns from database, determine the attribute whether is mySQL function, mySQL operates or string / numbers / boolean
     *
     * @param string $attr
     * @return string
     */
    private function process_data_attribute($attr)
    {
        if (str($attr) && is_string($attr)) {
            switch (trim($attr)) {
                // case "now()":
                // case "current_date":
                // case "current_date()":
                // case "current_timestamp":
                // case "current_timestamp()":
                // case "year(curdate())":
                // case "month(curdate())":
                // case "''":
                // // case (preg_match('/\s*\((^|\s|\b)(?!case\s\S)(.*?)\)\s*+/siU', $attr) ? true : false):
                case (preg_match('/\s*\((^|\s|\b)(case when\s\S)(.*?)\)\s*+/siU', $attr) ? true : false): // check the string is "case when"
                // case (preg_match("/^\(\w+\)($|\b)|(\w+)\(\)|^\w+\((?!case\s\S)(.*?)\)$/isU", $attr) ? true : false):
                case (preg_match("/^\(\S.*\)($|\b)|(\w+)\(\)|^\w+\((?!case\s\S)(.*?)\)$/isU", $attr) ? true : false): //Check FUNCTION(), FUNCTION(table_field, value), operator -- (1 + 2) or string contains "(" and ")"
                case (preg_match('/^\S*\(.*\)$/siU', $attr) ? true : false): // Check is function with arguments.  e.g: date_add(now(), INTERVAL 1 HOUR)
                    // case (preg_match("/^\(\w+\)($|\b)|(\w+)\(\)/isU", $attr) ? true : false):
                    // case (preg_match('/(adddate|addtime|convert_tz|date_add|date_format|date_diff|date_sub|day|dayname|dayofmonth|dayofweek|dayofyear|extract|from_days|from_unixtime|get_format|hour|last_day|makedate|maketime|microsecond|minute|month|monthname|period_add|period_diff|quarter|sec_to_time|second|str_to_date|subdate|subtime|time|time_format|time_to_sec|timediff|timestamp|timestampadd|timestampdiff|to_days|to_seconds|unix_timestamp|utc_date|utc_time|utc_timestamp|week|yearweek|weekday|weekofyear|year|yearweek)\((.?)\)($|\s|\b)/siU', $attr) ? true : false):
                    // case (preg_match('/\s*\((^|\s|\b)(?!case\s\S)(.*?)\)\s*+/siU', $attr) ? true : false):
                    // case (preg_match("/(?:(?:^(?!.*\bcase\b)|\G(?!\A)).*?)\K\b(?:\(|\))\b/gm", $attr, $reg) ? true : false):

                    // if (
                    //     !preg_match("/^\(select\s+(?<columns>.*?)\s+from\s+(.*?)?((where\s+(?<where>.*?))?(order by\s+(?<order>.*?))?(limit\s+(?<limit>(.*?)))?)$/sm", $attr)
                    //     &&
                    //     !preg_match('/\s*\((^|\s|\b)(case when\s\S)(.*?)\)\s*+/siU', $attr)
                    // ) {
                    //     preg_match('/^\((.*)\s*\)/siU', $attr, $reg); // To find out " ( ANY WORDS ) ", to determine it is string or mySQL operation

                    //     if ($reg[0] ?? false) {
                    //         $a = str_replace($reg[0], "", $attr); // After filtered " ( ANY WORDS ) " and still not blank, treat it as string instead of mySQL operation

                    //         if ($a ?? false) {
                    //             unset($a);
                    //             return "'" . trim(addslashes($attr)) . "'";
                    //         }
                    //     }

                    //     unset($reg);
                    // }

                    // if (!preg_match('/^\(((?!.*\()|(?!.*\))).*?\)$/', $attr)) {
                    //     // if found more than one " ( " and " ) ", treat it as string instead of mySQL operators / function
                    //     return "'" . trim(addslashes($attr)) . "'";
                    // }

                    return trim($attr);
                    break;

                case (substr($attr, 0, 1) == "'" && substr($attr, -1, 1) == "'" ? true : false): // Check the string begin and end has single quotes "'"
                    return "'" . trim(addslashes(substr($attr, 1, -1))) . "'";
                    break;

                case "''":
                    return trim($attr);
                    break;

                default:
                    $attr = $this->convert_bracket($attr, true);
                    return trim("'" . addslashes($attr) . "'");
                    break;
            }

            // if ($v != "now()" && $v != "current_date" && $v != "current_timestamp" && $v != "year(curdate())" && $v != "month(curdate())" && stripos($v, "(case when") === false && (substr($v, 0, 1) != "(" && substr($v, 1, -1) != ")")) {

            //     if (substr($v, 0, 1) == "(" && substr($v, 1, -1) == ")" && strpos($v, "+") !== false) {
            //         $this->_vars[$k] = "$v";
            //     } else {
            //         if ($v !== "''") {
            //             $this->_vars[$k] = "'$v'";
            //             $this->_vars[$k] = str_replace("''", "'", $this->_vars[$k]);
            //         } else {
            //             $this->_vars[$k] = "$v";
            //         }
            //     }
            // } else {
            //     $this->_vars[$k] = "$v";
            // }
        } else {
            if (is_numeric($attr) || is_float($attr) || is_bool($attr)) {
                return trim($attr);
            } else {
                return "null";
            }
        }
    }

    /**
     * Convert brackets to HTML code, or convert bracket HTML code to bracket
     *
     * @param array|string $inputs
     * @param bool $reverse
     * @return array|string
     */
    private function convert_bracket($inputs, $reverse = false)
    {
        // $search = array(
        //     "'\('i",
        //     "'\)'i",
        //     "'&#40;'i",
        //     "'&#41;'i",
        //     "'^\s+|\s+$'", //trim whitespace from beginning and end
        // );

        // $replace = array(
        //     "&#40;",
        //     "&#41;",
        //     "(",
        //     ")",
        //     "",
        // );

        // if (a($inputs)) {
        //     foreach ($inputs as $k => $v) {
        //         $inputs[$k] = preg_replace($search, $replace, $v);
        //     }

        //     return $inputs;
        // } else {
        //     return preg_replace($search, $replace, $inputs);
        // }

        if (a($inputs)) {
            foreach ($inputs as $k => $v) {
                if (!($this->_mysql_operator_symbol[$k] ?? false)) {
                    if ($reverse) {
                        $inputs[$k] = preg_replace(["'&#40;'i", "'&#41;'i", "'^\s+|\s+$'"], ["(", ")", ""], $v);
                    } else {
                        $inputs[$k] = preg_replace(["'\('i", "'\)'i", "'^\s+|\s+$'"], ["&#40;", "&#41;", ""], $v);
                    }
                }
            }

            return $inputs;
        } else {
            if ($this->_mysql_operator_symbol[$inputs] ?? false) {
            } else {
                if ($reverse) {
                    return str_replace(["&#40;", "&#41;"], ["(", ")"], trim($inputs));
                } else {
                    return str_replace(["(", ")"], ["&#40;", "&#41;"], trim($inputs));
                }
            }
        }
    }

    /**
     * Filter string to HTML or unix and remove whitespace
     *
     * @param array|string $inputs
     * @return array|string
     */
    private function filter_html($inputs)
    {
        $search = array(
            "'\('i",
            "'\)'i",
            "'^\s+|\s+$'", //trim whitespace from beginning and end
            "'&(quot|#10);'i", // Replace br
            "'&(quot|#60);br&(quot|#62);'i", // Replace br
            "'&(amp|#38);'i",
            "'&(copy|#169);'i",
        );

        $replace = array(
            "&#40;",
            "&#41;",
            "",
            "",
            PHP_EOL,
            PHP_EOL,
            "&",
            chr(169),
        );

        if (a($inputs)) {
            foreach ($inputs as $k => $v) {
                $inputs[$k] = preg_replace($search, $replace, $v);
            }

            return $inputs;
        } else {
            return preg_replace($search, $replace, $inputs);
        }
    }

    /**
     * Generate HTML5 view
     *
     * @param mixed $string
     * @param mixed $additional_string
     */
    private function throwout_html($string, $additional_string = null)
    {
        echo "<style>
                blockquote{
                    display:block;
                    background: #fff;
                    padding: 15px 20px 15px 45px;
                    margin: 0 0 20px;
                    position: relative;

                    /*Font*/
                    font-family: Georgia, serif;
                    font-size: 14px;
                    line-height: 1.2;
                    color: #666;

                    /*Box Shadow - (Optional)*/
                    -moz-box-shadow: 2px 2px 15px #ccc;
                    -webkit-box-shadow: 2px 2px 15px #ccc;
                    box-shadow: 2px 2px 15px #ccc;

                    /*Borders - (Optional)*/
                    border-left-style: solid;
                    border-left-width: 15px;
                    border-right-style: solid;
                    border-right-width: 2px;
                }

                blockquote::before{
                    content: \"\\201C\"; /*Unicode for Left Double Quote*/

                    /*Font*/
                    font-family: Georgia, serif;
                    font-size: 60px;
                    font-weight: bold;
                    color: #999;

                    /*Positioning*/
                    position: absolute;
                    left: 10px;
                    top:5px;
                }

                blockquote::after{
                /*Reset to make sure*/
                    content: \"\";
                }

                blockquote a{
                    text-decoration: none;
                    background: #eee;
                    cursor: pointer;
                    padding: 0 3px;
                    color: #c76c0c;
                }

                blockquote a:hover{
                    color: #666;
                }

                blockquote em{
                    font-style: italic;
                }

                /*Default Color Palette*/
                blockquote.default{
                    border-left-color: #656d77;
                    border-right-color: #434a53;
                }

                /*Grapefruit Color Palette*/
                blockquote.grapefruit{
                    border-left-color: #ed5565;
                    border-right-color: #da4453;
                }

                /*Bittersweet Color Palette*/
                blockquote.bittersweet{
                    border-left-color: #fc6d58;
                    border-right-color: #e95546;
                }

                /*Sunflower Color Palette*/
                blockquote.sunflower{
                    border-left-color: #ffcd69;
                    border-right-color: #f6ba59;
                }

                /*Grass Color Palette*/
                blockquote.grass{
                    border-left-color: #9fd477;
                    border-right-color: #8bc163;
                }

                /*Mint Color Palette*/
                blockquote.mint{
                    border-left-color: #46cfb0;
                    border-right-color: #34bc9d;
                }

                /*Aqua Color Palette*/
                blockquote.aqua{
                    border-left-color: #4fc2e5;
                    border-right-color: #3bb0d6;
                }

                /*Blue Jeans Color Palette*/
                blockquote.bluejeans{
                    border-left-color: #5e9de6;
                    border-right-color: #4b8ad6;
                }

                /*Lavander Color Palette*/
                blockquote.lavander{
                    border-left-color: #ad93e6;
                    border-right-color: #977bd5;
                }

                /*Pinkrose Color Palette*/
                blockquote.pinkrose{
                    border-left-color: #ed87bd;
                    border-right-color: #d870a9;
                }

                /*Light Color Palette*/
                blockquote.light{
                    border-left-color: #f5f7fa;
                    border-right-color: #e6e9ed;
                }

                /*Gray Color Palette*/
                blockquote.gray{
                    border-left-color: #ccd1d8;
                    border-right-color: #aab2bc;
                }

                blockquote.random{
                    border-left-color: " . sprintf('#%06X', mt_rand(0, 0xFFFFFF)) . ";
                    border-right-color: #" . substr(str_shuffle('AABBCCDDEEFF00112233445566778899AABBCCDDEEFF00112233445566778899AABBCCDDEEFF00112233445566778899'), 0, 6) . ";
                }

                blockquote.random-sql{
                    border-left-color: #" . substr(str_shuffle('AABBCCDDEEFF00112233445566778899AABBCCDDEEFF00112233445566778899AABBCCDDEEFF00112233445566778899'), 0, 6) . ";
                    border-right-color: " . sprintf('#%06X', mt_rand(0, 0xFFFFFF)) . ";
                }
                </style>
                        ";

        $colors = [
            "default",
            "grapefruit",
            "bittersweet",
            "sunflower",
            "grass",
            "mint",
            "aqua",
            "bluejeans",
            "lavander",
            "pinkrose",
            "light",
            "gray",
        ];

        $random = array_rand($colors, 1);
        // $color  = $colors[$random];
        $color = "random";

        if (is_value($additional_string)) {
            echo "<blockquote class=\"$color\">
                <code style=\"font-size: 1.5em;\">
                $additional_string
                </code>
                </blockquote>
                <blockquote class=\"$color-sql\">
                <p></p>
                <h1><span class=\"grapefruit\">" . $string . "</span></h1>
                </blockquote>";
        } else {
            echo "<blockquote class=\"$color\">
                <h1><span class=\"grapefruit\">" . $string . "</span></h1>
                </blockquote>";
        }

        // echo "<blockquote class=\"$color\">
        //         <h1><span class=\"grapefruit\">" . $string . "</span></h1>
        //         <p></p>
        //         <code>
        //         $additional_string
        //         </code>
        //         </blockquote>";

        unset($colors);
        unset($color);
        unset($random);
    }

    private function getip()
    {
        $IP = '';

        if (getenv('HTTP_CLIENT_IP')) {
            $IP = getenv('HTTP_CLIENT_IP');
        } else if (getenv('HTTP_X_FORWARDED_FOR')) {
            $IP = getenv('HTTP_X_FORWARDED_FOR');
        } else if (getenv('HTTP_X_FORWARDED')) {
            $IP = getenv('HTTP_X_FORWARDED');
        } else if (getenv('HTTP_FORWARDED_FOR')) {
            $IP = getenv('HTTP_FORWARDED_FOR');
        } else if (getenv('HTTP_FORWARDED')) {
            $IP = getenv('HTTP_FORWARDED');
        } else {
            $IP = $_SERVER['REMOTE_ADDR'];
        }

        return $IP;
    }
}
