<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 11:36 下午.
 */

namespace HughCube\Laravel\AliOSS\Tests;

use HughCube\Laravel\AliOSS\ServiceProvider as PackageServiceProvider;
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
        parent::getEnvironmentSetUp($app);

        /** @var Repository $config */
        $config = $app['config'];

        $config->set('filesystems.disks.alioss', [
            'driver' => 'alioss',
            'accessKeyId' => env('ALIOSS_ACCESS_KEY_ID'),
            'accessKeySecret' => env('ALIOSS_ACCESS_KEY_SECRET'),
            'endpoint' => env('ALIOSS_ENDPOINT'),
            'bucket' => env('ALIOSS_BUCKET'),
            'isCName' => env('ALIOSS_IS_CNAME'),
            'securityToken' => env('ALIOSS_SECURITY_TOKEN'),
            'requestProxy' => env('ALIOSS_REQUEST_PROXY'),
        ]);
    }
}
