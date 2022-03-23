<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/18
 * Time: 10:32 下午.
 */

namespace HughCube\Laravel\Package;

use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use Laravel\Lumen\Application as LumenApplication;

class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Boot the provider.
     */
    public function boot()
    {
        $source = realpath(dirname(__DIR__).'/config/config.php');

        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([$source => config_path(sprintf("%s.php", Package::getFacadeAccessor()))]);
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure(Package::getFacadeAccessor());
        }
    }

    /**
     * Register the provider.
     */
    public function register()
    {
        $this->app->singleton(Package::getFacadeAccessor(), function ($app) {
            return new Manager();
        });
    }
}
