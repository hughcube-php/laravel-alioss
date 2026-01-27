<?php

namespace HughCube\Laravel\AliOSS\Tests;

use BadMethodCallException;
use Dotenv\Dotenv;
use HughCube\Laravel\AliOSS\OssAdapter;
use HughCube\Laravel\AliOSS\ServiceProvider;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        $this->loadEnv();
        $this->configureSsl();
        parent::setUp();
    }

    protected function loadEnv(): void
    {
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $dotenv = Dotenv::createImmutable(dirname(__DIR__));
            $dotenv->safeLoad();
        }
    }

    protected function configureSsl(): void
    {
        // 配置 SSL 证书路径，解决 Windows 环境下的 SSL 证书问题
        $caFile = __DIR__ . '/cacert.pem';
        if (file_exists($caFile)) {
            ini_set('curl.cainfo', $caFile);
            ini_set('openssl.cafile', $caFile);
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            FilesystemServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filesystems.disks.oss', [
            'driver' => 'alioss',
            'accessKeyId' => env('ALIOSS_ACCESS_KEY_ID', 'test-access-key-id'),
            'accessKeySecret' => env('ALIOSS_ACCESS_KEY_SECRET', 'test-access-key-secret'),
            'endpoint' => env('ALIOSS_ENDPOINT', 'oss-cn-hangzhou.aliyuncs.com'),
            'bucket' => env('ALIOSS_BUCKET', 'test-bucket'),
            'regionId' => env('ALIOSS_REGION_ID', 'cn-hangzhou'),
            'isCName' => env('ALIOSS_IS_CNAME', false),
            'prefix' => env('ALIOSS_PREFIX', ''),
            'cdnBaseUrl' => env('ALIOSS_CDN_BASE_URL', 'https://cdn.example.com'),
            'uploadBaseUrl' => env('ALIOSS_UPLOAD_BASE_URL', 'https://upload.example.com'),
        ]);
    }

    protected function getOssAdapter(): OssAdapter
    {
        $disk = Storage::disk('oss');

        $adapter = $disk instanceof FilesystemAdapter ? $disk->getAdapter() : null;
        if (!$adapter instanceof OssAdapter) {
            throw new BadMethodCallException('Can only be called to alioss drives!');
        }

        return $adapter;
    }

    protected function createMockAdapter(array $config = []): OssAdapter
    {
        $defaultConfig = [
            'accessKeyId' => env('ALIOSS_ACCESS_KEY_ID', 'test-access-key-id'),
            'accessKeySecret' => env('ALIOSS_ACCESS_KEY_SECRET', 'test-access-key-secret'),
            'endpoint' => env('ALIOSS_ENDPOINT', 'oss-cn-hangzhou.aliyuncs.com'),
            'bucket' => env('ALIOSS_BUCKET', 'test-bucket'),
            'regionId' => env('ALIOSS_REGION_ID', 'cn-hangzhou'),
            'isCName' => env('ALIOSS_IS_CNAME', false),
            'prefix' => '',
            'cdnBaseUrl' => env('ALIOSS_CDN_BASE_URL', 'https://cdn.example.com'),
            'uploadBaseUrl' => env('ALIOSS_UPLOAD_BASE_URL', 'https://upload.example.com'),
        ];

        return new OssAdapter(array_merge($defaultConfig, $config));
    }

    protected function setupMockOssDisk(?OssAdapter $adapter = null): void
    {
        $adapter = $adapter ?? $this->createMockAdapter();

        Storage::extend('alioss', function ($app, $config) use ($adapter) {
            return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
        });
    }
}
