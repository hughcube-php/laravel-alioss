<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 11:36 下午.
 */

namespace HughCube\Laravel\Package\Tests;

use HughCube\Laravel\Package\Package;
use HughCube\Laravel\Package\ServiceProvider as PackageServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    /**
     * @param  Application  $app
     *
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            PackageServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $this->setupCache($app);

        /** @var Repository $appConfig */
        $appConfig = $app['config'];
        $appConfig->set(
            Package::getFacadeAccessor(),
            (require dirname(__DIR__).'/config/config.php')
        );
    }

    /**
     * @param  Application  $app
     */
    protected function setupCache(Application $app)
    {
        /** @var Repository $appConfig */
        $appConfig = $app['config'];

        $appConfig->set('cache', [
            'default' => 'default',
            'stores' => [
                'default' => [
                    'driver' => 'file',
                    'path' => sprintf('/tmp/test/%s', md5(serialize([__METHOD__]))),
                ],
            ],
        ]);
    }
}
