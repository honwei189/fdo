<?php
/*
 * @creator           : Gordon Lim <honwei189@gmail.com>
 * @created           : 06/05/2019 21:54:39
 * @last modified     : 23/12/2019 21:46:48
 * @last modified by  : Gordon Lim <honwei189@gmail.com>
 */

namespace honwei189\fdo;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

/**
 *
 * Data Operate service provider (for Laravel)
 *
 *
 * @package     fdo
 * @subpackage
 * @author      Gordon Lim <honwei189@gmail.com>
 * @link        https://github.com/honwei189/html/
 * @version     "1.0.0" 
 * @since       "1.0.0" 
 */
class fdoServiceProvider extends ServiceProvider
{
    /**
     * Register service
     *
     * @return void
     */
    public function register()
    {
        //$this->app->bind(fdo::class);

        $this->app->booting(function() {
            $loader = AliasLoader::getInstance();
            $loader->alias('fdo', fdo::class);
        });
    }

    /**
     * Load service on start-up
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton('fdo', function () {
            return new fdo;
        });
    }

    public function provides()
    {
        //return ['fdo'];
    }
}
