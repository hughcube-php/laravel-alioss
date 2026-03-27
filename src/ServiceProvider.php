<?php

namespace HughCube\Laravel\AliOSS;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use League\Flysystem\Filesystem;

class ServiceProvider extends IlluminateServiceProvider
{
    public function boot(): void
    {
        Storage::extend('alioss', function ($app, $config) {
            $adapter = new OssAdapter($config);

            return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
        });
    }
}
