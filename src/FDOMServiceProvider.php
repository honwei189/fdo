<?php
/**
 * Description   :
 * ---------
 * Created       : 2021-09-17 03:40:23 pm
 * Author        : Gordon Lim
 * Last Modified : 2021-09-17 03:41:26 pm
 * Modified By   : Gordon Lim
 * ---------
 * Changelog
 *
 * Date & time           By                    Version   Comments
 * -------------------   -------------------   -------   ---------------------------------------------------------
 *
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
        $this->app->booting(function () {
            $loader = AliasLoader::getInstance();
            // $loader->alias('FDO', FDO::class);
            $loader->alias(FDOM::class, 'fdom');
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

        $this->app->make('honwei189\FDO\FDOM');
    }

    public function provides()
    {
        //return ['FDO'];
    }
}
