<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/18
 * Time: 10:32 下午.
 */

namespace HughCube\Laravel\AliOSS;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use League\Flysystem\Filesystem;

class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Boot the provider.
     */
    public function boot()
    {
        Storage::extend('alioss', function ($app, $config) {
            $adapter = new OssAdapter($config);
            return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
        });
    }
}
