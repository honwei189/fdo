<?php
/*
 * @creator           : Gordon Lim <honwei189@gmail.com>
 * @created           : 06/05/2019 21:54:39
 * @last modified     : 05/06/2020 21:16:54
 * @last modified by  : Gordon Lim <honwei189@gmail.com>
 */

namespace honwei189\FDO;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

/**
 *
 * Data Operate service provider (for Laravel)
 *
 *
 * @package     FDO
 * @subpackage
 * @author      Gordon Lim <honwei189@gmail.com>
 * @link        https://github.com/honwei189/fdo/
 * @version     "1.0.0"
 * @since       "1.0.0"
 */
class FDOServiceProvider extends ServiceProvider
{
    /**
     * Register service
     *
     * @return void
     */
    public function register()
    {
        // $this->app->bind(FDO::class);
        $this->app->booting(function() {
            $loader = AliasLoader::getInstance();
            // $loader->alias('FDO', FDO::class);
            $loader->alias(FDO::class, 'fdo'); // FDO has been loaded by Laravel, this method may wrong and may not be needed anymore
            // $loader->alias(FDOM::class, 'fdom');
            $loader->alias('fdom', FDOM::class); // Corret method and to register FDOM to boot loader and create alias
        });

        // App::bind('FDO', function()
        // {
        //     return new FDO;
        // });
    }

    /**
     * Load service on start-up
     *
     * @return void
     */
    public function boot()
    {
        // $this->app->singleton('FDO', function () {
        //     return new FDO;
        // });

        $this->app->make('honwei189\FDO');
        $this->app->make('honwei189\FDO\FDOM');
    }

    public function provides()
    {
        //return ['FDO'];
    }
}
