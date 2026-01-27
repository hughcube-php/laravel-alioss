<?php

namespace HughCube\Laravel\AliOSS\Tests;

use HughCube\Laravel\AliOSS\OssAdapter;
use HughCube\Laravel\AliOSS\ServiceProvider;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class ServiceProviderTest extends TestCase
{
    public function testServiceProviderIsRegistered(): void
    {
        $providers = $this->app->getLoadedProviders();
        $this->assertArrayHasKey(ServiceProvider::class, $providers);
    }

    public function testAliossDriverIsExtended(): void
    {
        $disk = Storage::disk('oss');
        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
    }

    public function testAliossDriverReturnsOssAdapter(): void
    {
        $disk = Storage::disk('oss');
        $adapter = $disk->getAdapter();
        $this->assertInstanceOf(OssAdapter::class, $adapter);
    }

    public function testConfigurationIsPassedToAdapter(): void
    {
        $adapter = $this->getOssAdapter();

        $this->assertNotEmpty($adapter->getBucket());
        $this->assertNotNull($adapter->getOssClient());
    }
}
