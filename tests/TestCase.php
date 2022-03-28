<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 11:36 下午.
 */

namespace HughCube\Laravel\AliOSS\Tests;

use BadMethodCallException;
use HughCube\GuzzleHttp\HttpClientTrait;
use HughCube\Laravel\AliOSS\OssAdapter;
use HughCube\Laravel\AliOSS\ServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class TestCase extends OrchestraTestCase
{
    use HttpClientTrait;

    /**
     * @param Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            FilesystemServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    /**
     * @param object|string  $object $object
     * @param string        $method
     * @param array         $args
     *
     * @return mixed
     *@throws ReflectionException
     *
     */
    protected static function callMethod(object|string $object, string $method, array $args = []): mixed
    {
        $class = new ReflectionClass($object);

        /** @var ReflectionMethod $method */
        $method = $class->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs((is_object($object) ? $object : null), $args);
    }

    /**
     * @param object $object $object
     * @param string $name
     *
     * @throws ReflectionException
     *
     * @return mixed
     */
    protected static function getProperty(object $object, string $name): mixed
    {
        $class = new ReflectionClass($object);

        $property = $class->getProperty($name);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * @param object $object
     * @param string $name
     * @param mixed  $value
     *
     * @throws ReflectionException
     */
    protected static function setProperty(object $object, string $name, mixed $value)
    {
        $class = new ReflectionClass($object);

        $property = $class->getProperty($name);
        $property->setAccessible(true);

        $property->setValue($object, $value);
    }

    /**
     * @param Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        /** @var Repository $config */
        $config = $app['config'];

        $config->set('filesystems.disks.alioss', [
            'driver' => 'alioss',

            'prefix' => '/oss-test/',

            'accessKeyId'     => env('ALIOSS_ACCESS_KEY_ID'),
            'accessKeySecret' => env('ALIOSS_ACCESS_KEY_SECRET'),
            'endpoint'        => env('ALIOSS_ENDPOINT'),
            'bucket'          => env('ALIOSS_BUCKET'),
            'isCName'         => env('ALIOSS_IS_CNAME'),
            'securityToken'   => env('ALIOSS_SECURITY_TOKEN'),
            'requestProxy'    => env('ALIOSS_REQUEST_PROXY'),
        ]);
    }

    protected function getOssAdapter(): OssAdapter
    {
        /** @var FilesystemManager $manager */
        $manager = $this->app['filesystem'];
        $disk = $manager->disk('alioss');

        /** @var OssAdapter $adapter */
        $adapter = $disk instanceof FilesystemAdapter ? $disk->getAdapter() : null;
        if (!$adapter instanceof OssAdapter) {
            throw new BadMethodCallException('Can only be called to alioss drives!');
        }

        return $adapter;
    }

    /**
     * @throws
     * @phpstan-ignore-next-line
     */
    public function caseWithClear(callable $callback)
    {
        $adapter = $this->getOssAdapter();

        $callback($adapter);

        $callback($adapter->withBucket($adapter->getBucket()));

        $callback($adapter->withConfig(['prefix' => null]));

        $options = ['delimiter' => '', 'prefix' => 'oss-test/', 'max-keys' => 1000, 'marker' => ''];
        $result = $adapter->getOssClient()->listObjects($adapter->getBucket(), $options);

        $objects = [];
        foreach ($result->getObjectList() as $object) {
            if (in_array($object->getKey(), ['oss-test/', 'oss-test'])) {
                continue;
            }

            $objects[] = $object->getKey();
        }
        if (empty($objects)) {
            return;
        }

        $adapter->deleteObjects($adapter->getBucket(), $objects);
    }
}
