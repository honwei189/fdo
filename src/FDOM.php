<?php
/*
 * @creator           : Gordon Lim <honwei189@gmail.com>
 * @created           : 06/05/2019 21:54:39
 * @last modified     : 23/12/2019 21:46:22
 * @last modified by  : Gordon Lim <honwei189@gmail.com>
 */

namespace honwei189\FDO;

use honwei189\FDO\SQL;

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
    static $instance = null;
    static $rc;
    protected static $table;
    protected static $id;

    public function __call($name, $arguments)
    {
        self::get_table();
        return self::call($name, $arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        self::get_table();
        return self::call($name, $arguments);
    }

    public function __set($name, $val)
    {
        self::call("__set", [$name, $val]);
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
     * @return Response
     */
    public static function create()
    {
        //
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
     * @return Response
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
    public static function edit($id)
    {
        //
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
        return self::call("is_exists_id", $id);
    }

    public static function save()
    {
        $request = app("request");

        // $flayer = app("flayer");
        // $flayer::bind("\\honwei189\\http");
        // $http = $flayer->http();
        // pre($http->get());

        $post = $request->post();

        if (isset($post) && is_array($post) && count($post) > 0) {
            foreach ($post as $k => $v) {
                self::call_vars($k, $v);
            }
        }

        self::call("debug");
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

    private static function call($name, $arguments = [])
    {
        if (!is_array($arguments)) {
            $arguments = [$arguments];
        }

        // if (!is_value(self::$table)) {
        //     self::get_table();
        // }

        return call_user_func_array(array(self::load_instance(), $name), $arguments);
    }

    private static function call_vars($k, $v)
    {
        return self::load_instance()->$k = $v;
    }

    private static function get_table()
    {
        // self::get_var("_table");
        if (!is_value(static::$table)) {
            if (!is_value(self::call("get_table"))) {
                $p = get_called_class();

                if (strpos($p, "\\") !== false) {
                    $_ = explode("\\", $p);

                    // call_user_func_array(array(self::load_instance(), "set_table"), [end($_)]);
                    self::call("set_table", [end($_)]);
                    unset($_);
                }

                unset($p);
            }
        } else {
            // self::call("debug");
            call_user_func_array(array(self::load_instance(), "set_table"), [static::$table]);
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
            if(function_exists("fdo")){
                self::$instance = app("fdo");
            }else{
                self::$instance = new SQL;
            }
        }

        return self::$instance;
    }
}
