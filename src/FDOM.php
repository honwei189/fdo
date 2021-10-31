<?php
/*
 * @creator           : Gordon Lim <honwei189@gmail.com>
 * @created           : 06/05/2019 21:54:39
 * @last modified     : 23/12/2019 21:46:22
 * @last modified by  : Gordon Lim <honwei189@gmail.com>
 */

namespace honwei189\FDO;

/**
 * Data Operate static model.  Applicable for Laravel -- model or anyone would like to use static class to execute FDO
 *
 * This provides various useful library function, and also DB query ( you can use it to replace Laravel Eloquent )
 *
 * It is easy to use and more faster than Laravel Eloquent, and also fast coding
 *
 * Usage:
 *
 * In your model:
 *
 * use honwei189\FDO\FDOM;
 *
 * class YOUR_CLASS extends fdom {
 *
 * }
 *
 * or;
 *
 * $app->bind("honwei189\\FDO\\FDOM");
 * $app->FDOM()::connect(config::get("db", "mysql"));
 * echo $app->FDOM()::user()->debug()->find();
 *
 * or;
 *
 * echo FDOM::version();
 * FDOM::user()->debug()->find();
 *
 *
 * @package     FDO
 * @subpackage
 * @author      Gordon Lim <honwei189@gmail.com>
 * @link        https://github.com/honwei189/fdo/
 * @version     "1.0.0"
 * @since       "1.0.0"
 */
class FDOM
{
    private static $instance        = null;
    private static $manual_fill     = false;
    private static $parent_instance = null;
    private static $rc;
    // private $_methods = [];
    protected static $_fillable;
    protected static $_table;
    protected $attributes;
    protected $data;
    protected $id;

    public function __construct()
    {

    }

    public function __call($name, $arguments)
    {
        // if ($name == "creates"){
        //     var_dump($arguments[1]);

        //     if ($arguments[1] instanceof \Closure) {

        //     }
        //     return $this->_methods[$arguments[0]] = $arguments[1];
        // }

        if (isset($this->_methods[$name])) {
            return $this->_methods[$name];
        }

        if ($this->$name ?? false) {
            return $this->$name;
        }

        // self::get_table();
        return self::call($name, $arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        self::get_table();
        return self::call($name, $arguments);
    }

    public function __get($name)
    {
        return ($this->$name ?? self::call("__get", [$name]));
    }

    public function __set($name, $val)
    {
        self::call("__set", [$name, $val]);
    }

    public function creates($name, \Closure $closure)
    {
        // if (!$this->_methods ?? false){
        //     $this->_methods = [];
        // }
        // $this->_methods[$name] = $closure->__invoke();
        $this->$name = $closure->__invoke();

        // if (is_null($this->_methods[$name])) {
        if (is_null($this->$name)) {
            ob_start();
            // $this->_methods[$name];
            $closure->__invoke();
            $output = ob_get_contents();
            ob_end_clean();

            // $this->_methods[$name] = $output;
            $this->$name = $output;
            return;
        }
    }

    /**
     * Show all data from db
     *
     * @return Response
     */
    public static function all($where = null)
    {
        return self::call("where", $where)->find();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param mixed $data Array type.  e.g: ["gender" => "M", "name" => "The name"]
     * @return FDO
     */
    public static function create($data = null)
    {
        return self::save($data);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public static function destroy($id)
    {
        if (is_array($id) && count($id) > 0) {
            $_  = "id in (" . join(", ", $id) . ")";
            $id = null;
            $id = $_;
            unset($_);
        } else if (isset($id) && is_value($id)) {
            $id = "id = $id";
        }

        return self::call("where", $id)->delete();
    }

    /**
     * Set SQL value.  e.g:  abc::set("name", "My name"); //output = insert into abc (name) values ('My name');
     * @param string $name table field name
     * @param string $val value
     * @return fdo
     */
    public static function set($name, $val)
    {
        // self::call("__set", [$name, $val]);
        return self::load_instance()->$name = $val;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return FDO
     */
    public static function store()
    {
        return self::call("store");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public static function edit($data = null, $id = null)
    {
        return self::save($data);
    }

    /**
     * Fetch data from DB with single id or multiple id (array type)
     *
     * @param integer|array $id
     * @return Response
     */
    public static function find($id = null)
    {
        if (is_array($id) && count($id) > 0) {
            $_  = "id in (" . join(", ", $id) . ")";
            $id = null;
            $id = $_;
            unset($_);
        } else if (isset($id) && is_value($id)) {
            $id = "id = $id";
        }

        return self::call("where", $id)->find();
    }

    public static function findOrFail($id)
    {
        self::get_table();
        $o = new static;
        // return self::call("is_exists_id", $id) && self::$id = $id;
        // self::$data = self::call("get", [self::$_table, "*", $id]);
        // $o::$data = self::call("by_id", $id)->get();
        $o->data = self::call("by_id", $id)->get();

        if (is_array($o->data) && count($o->data) > 0) {
            // pre(self::$parent_instance->fillable);
            // $o::set("fillable", ['dd']);

            $o->id = $id;
            // self::$id = $id;

            // self::set("_id", $id);
            return $o;
            // return self::$instance;
        }

        return false;
    }

    public static function latest()
    {
        self::get_table();

        // return ( new static )::call("where", ["is_active", "1"])->fetch_mode(\PDO::FETCH_INTO, static::class)->order_by("id", "desc");
        // return ( new static )::call("where", ["is_active", "1"])->fetch_mode(\PDO::FETCH_INTO)->order_by("id", "desc");

        // return ( new static )::call("where", ["is_active", "1"])->fetch_mode(\PDO::FETCH_CLASS)->order_by("id", "desc");

        return (new static )::call("where", ["is_active", "1"])->order_by("id", "desc");
    }

    /**
     * Save data into database
     *
     * @param mixed $data Array type.  e.g: ["gender" => "M", "name" => "The name"]
     * @return FDO
     */
    public static function save($data = null)
    {
        if (!self::$manual_fill) {
            self::build_save_update($data);
        }

        self::$manual_fill = false;

        // self::call("debug");
        return self::call("save");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public static function show($id = null)
    {
        if (is_null($id)) {
            $id = self::$id;
        }

        if (!is_int($id)) {
            $id = self::call("get_id", $id);
        }

        return self::call("by_id", $id)->read();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public static function update($id = null)
    {
        if (is_null($id)) {
            $id = self::$id;
        }

        if (!self::$manual_fill) {
            self::build_save_update();
        }

        self::$manual_fill = false;

        if (!is_null($id)) {
            return self::call("by_id", $id)->update();
        } else {
            return self::call("update");
        }
    }

    public static function where($s, $v = null)
    {
        return self::call("where", [$s, $v]);
    }

    private static function build_save_update($data = null)
    {
        if (is_null($data) && !is_array($data)) {
            $request = (object) app("request");

            // $flayer = app("flayer");
            // $flayer::bind("\\honwei189\\http");
            // $http = $flayer->http();
            // pre($http->get());

            $data = $request->post();
        }

        self::$instance->fill($data);

        // if (is_array(self::$instance->fillable) && count(self::$instance->fillable) > 0) {
        //     $match = false;

        //     foreach (self::$instance->fillable as $v) {
        //         if (isset($data[$v])) {
        //             self::call_vars($v, ($data[$v]));
        //             $match = true;
        //         }
        //     }

        //     if (!$match) {
        //         die("<h1>No data columns matched with " . get_called_class() . " ->\$fillable - " . implode(", ", self::$instance->fillable) . "</h1>");
        //     }
        // } else {
        //     if (isset($data) && is_array($data) && count($data) > 0) {
        //         foreach ($data as $k => $v) {
        //             switch ($k) {
        //                 case "_token":
        //                 case "_method":
        //                     break;

        //                 default:
        //                     self::call_vars($k, $v);
        //                     break;
        //             }
        //         }
        //     }
        // }
    }

    private static function call($name, $arguments = [])
    {
        if (!is_array($arguments)) {
            $arguments = [$arguments];
        }

        // if (!is_value(self::$table)) {
        self::get_table();
        // }

        if ($name == "fill" || $name == "fillmap" || $name == "fillonly") {
            self::$manual_fill = true;
        }

        return call_user_func_array(array(self::$instance, $name), $arguments);
    }

    private static function call_vars($k, $v)
    {
        return self::load_instance()->$k = $v;
    }

    private static function get_table()
    {
        // pre(self::get_var("fillable"));

        // self::get_var("_table");

        self::load_instance();

        if (is_object(self::$parent_instance)) {
            self::$_table             = self::$parent_instance->table ?? "";
            self::$instance->fillable = self::$parent_instance->fillable ?? [];
            self::$instance->parent   = static::class;
        }

        if (class_exists("Auth")) {
            self::$instance->username = ("\Auth")::user()->username ?? null;
            self::$instance->user_id  = ("\Auth")::id() ?? null;
        }

        if (!str(static::$_table)) {
            $tbl = self::call("get_table");

            if (!str($tbl)) {
                $p = get_called_class();

                if (strpos($p, "\\") !== false) {
                    $_ = explode("\\", $p);

                    // call_user_func_array(array(self::load_instance(), "set_table"), [end($_)]);
                    // self::call("set_table", [strtolower(end($_))]);
                    call_user_func_array(array(self::$instance, "set_table"), [end($_)]);
                    unset($_);
                }

                unset($p);
            }

            unset($tbl);
        } else {
            // pre(self::$instance);
            // self::call("debug");
            // call_user_func_array(array(self::load_instance(), "set_table"), [static::$_table]);
            call_user_func_array(array(self::$instance, "set_table"), [static::$_table]);
        }
        // return self::load_instance($name, $arguments);
        // return self::load_instance()->$name($arguments);
    }

    private static function get_var($name)
    {
        self::load_instance();
        echo self::$instance->$name;
    }

    private static function load_instance($name = null, $arguments = null)
    {
        if (!self::$instance) {
            $p = get_called_class();

            if (function_exists("app")) {
                self::$instance = app((class_exists("FDO") ? "FDO" : "fdo"));

                if (is_value($p)) {
                    self::$parent_instance = app($p);

                    if (!is_object(self::$parent_instance)) {
                        self::$parent_instance = new $p;
                    }
                }
            } else {
                self::$instance = new SQL;

                if (is_value($p)) {
                    self::$parent_instance = new $p;
                }
            }
        }

        self::$instance->fetch_mode(\PDO::FETCH_ASSOC);

        if (is_object(self::$instance) && self::$instance->is_laravel()) {
            // self::prefill(["created_by", "created_at"], [self::get_user_id(), "now()"]);
            // self::$instance->prefill(["created_by", "created_at", "updated_by", "updated_at"], [self::get_user_id(), "now()", self::get_user_id(), "now()"]);
            self::$instance->prefill(["created_by", "created_at", "updated_by", "updated_at"], [self::$instance->get_user_id(), "now()", self::$instance->get_user_id(), "now()"]);
        }
        return self::$instance;
    }
}
