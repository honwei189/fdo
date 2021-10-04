<?php
/**
 * Description   : 
 * ---------
 * Created       : 2021-09-12 08:29:12 pm
 * Author        : Gordon Lim
 * Last Modified : 2021-10-04 09:15:40 pm
 * Modified By   : Gordon Lim
 * ---------
 * Changelog
 * 
 * Date & time           By                    Version   Comments
 * -------------------   -------------------   -------   ---------------------------------------------------------
 * 
*/

namespace honwei189\FDO;

/**
 *
 * FDO data object
 *
 *
 * @package     FDO
 * @subpackage
 * @author      Gordon Lim <honwei189@gmail.com>
 * @link        https://github.com/honwei189/fdo/
 * @version     
 * @since       "1.0.0"
 */
class fdoData
{
    public function __call($name, $arguments)
    {
        if ($this->$name ?? false) {
            return $this->$name;
        }
    }

    public function __get($name)
    {
        return $this->$name ?? "";
    }

    public function __set($name, $val)
    {
        // if (is_callable($val)) {
        //     $closure = \Closure::bind($val, $this);
        //     return call_user_func($closure);
        // }
        $this->$name = $val;
    }

    public function creates($name, \Closure $closure)
    {
        $this->$name = $closure->__invoke();

        // if (is_null($this->$name)) {
        //     ob_start();
        //     // $this->_methods[$name];
        //     $closure->__invoke();
        //     $output = ob_get_contents();
        //     ob_end_clean();

        //     // $this->_methods[$name] = $output;
        //     $this->$name = $output;
        //     return;
        // }

        $closure = $closure->bindTo($this);

        return call_user_func($closure);
    }
}
