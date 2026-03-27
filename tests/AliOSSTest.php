<?php

namespace HughCube\Laravel\AliOSS\Tests;

use BadMethodCallException;
use HughCube\Laravel\AliOSS\AliOSS;
use HughCube\Laravel\AliOSS\OssAdapter;

class AliOSSTest extends TestCase
{
    public function testGetClient(): void
    {
        $this->assertInstanceOf(OssAdapter::class, AliOSS::getClient('oss'));
    }

    public function testGetClientWithDefaultDisk(): void
    {
        $this->assertInstanceOf(OssAdapter::class, AliOSS::getClient());
    }

    public function testGetClientThrowsForNonOssDisk(): void
    {
        $this->app['config']->set('filesystems.disks.local', [
            'driver' => 'local',
            'root' => storage_path('app'),
        ]);

        $this->expectException(BadMethodCallException::class);
        AliOSS::getClient('local');
    }

    public function testWatermarkText(): void
    {
        $text = 'Hello World';
        $encoded = AliOSS::watermarkText($text);
        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $decoded = base64_decode(strtr($encoded, ['-' => '+', '_' => '/']));
        $this->assertSame($text, $decoded);
    }
}
