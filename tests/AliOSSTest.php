<?php

namespace HughCube\Laravel\AliOSS\Tests;

use BadMethodCallException;
use HughCube\Laravel\AliOSS\AliOSS;
use HughCube\Laravel\AliOSS\OssAdapter;

class AliOSSTest extends TestCase
{
    public function testGetClient(): void
    {
        $adapter = AliOSS::getClient('oss');
        $this->assertInstanceOf(OssAdapter::class, $adapter);
    }

    public function testGetClientWithDefaultDisk(): void
    {
        $adapter = AliOSS::getClient();
        $this->assertInstanceOf(OssAdapter::class, $adapter);
    }

    public function testGetClientThrowsExceptionForNonOssDisk(): void
    {
        $this->app['config']->set('filesystems.disks.local', [
            'driver' => 'local',
            'root' => storage_path('app'),
        ]);

        $this->expectException(BadMethodCallException::class);
        AliOSS::getClient('local');
    }

    public function testBase64EncodeWatermarkText(): void
    {
        $text = 'Hello World';
        $encoded = AliOSS::base64EncodeWatermarkText($text);

        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);

        $decoded = base64_decode(strtr($encoded, ['-' => '+', '_' => '/']));
        $this->assertSame($text, $decoded);
    }

    public function testStaticCallForwarding(): void
    {
        $bucket = AliOSS::getBucket();
        $this->assertNotEmpty($bucket);

        $prefixer = AliOSS::getPrefixer();
        $this->assertNotNull($prefixer);
    }

    public function testIsBucketUrlViaFacade(): void
    {
        $adapter = $this->getOssAdapter();
        $cdnBaseUrl = $adapter->getCdnBaseUrl();

        if ($cdnBaseUrl) {
            $this->assertTrue(AliOSS::isBucketUrl($cdnBaseUrl . '/test.jpg'));
        }

        $this->assertFalse(AliOSS::isBucketUrl('https://other.example.com/file.jpg'));
    }
}
