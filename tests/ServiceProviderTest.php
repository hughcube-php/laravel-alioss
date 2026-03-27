<?php

namespace HughCube\Laravel\AliOSS\Tests;

use AlibabaCloud\Oss\V2 as Oss;
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

    public function testAliossDriverReturnsOssAdapter(): void
    {
        $disk = Storage::disk('oss');
        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        $this->assertInstanceOf(OssAdapter::class, $disk->getAdapter());
    }

    public function testConfigurationIsPassedToAdapter(): void
    {
        $adapter = $this->getOssAdapter();
        $this->assertNotEmpty($adapter->bucket());
        $this->assertInstanceOf(Oss\Client::class, $adapter->client());
    }
}
