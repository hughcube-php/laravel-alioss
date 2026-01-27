<?php

namespace HughCube\Laravel\AliOSS\Tests;

use HughCube\Laravel\AliOSS\AliOSS;
use HughCube\Laravel\AliOSS\OSS;
use HughCube\Laravel\AliOSS\OssAdapter;

class OSSTest extends TestCase
{
    public function testOSSExtendsAliOSS(): void
    {
        $this->assertTrue(is_subclass_of(OSS::class, AliOSS::class));
    }

    public function testGetClient(): void
    {
        $adapter = OSS::getClient('oss');

        $this->assertInstanceOf(OssAdapter::class, $adapter);
    }

    public function testBase64EncodeWatermarkText(): void
    {
        $text = 'Test Text';
        $encoded = OSS::base64EncodeWatermarkText($text);

        $this->assertSame(AliOSS::base64EncodeWatermarkText($text), $encoded);
    }

    public function testStaticCallForwarding(): void
    {
        $bucket = OSS::getBucket();
        $this->assertNotEmpty($bucket);

        $prefixer = OSS::getPrefixer();
        $this->assertNotNull($prefixer);
    }
}
