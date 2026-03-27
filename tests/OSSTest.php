<?php

namespace HughCube\Laravel\AliOSS\Tests;

use HughCube\Laravel\AliOSS\AliOSS;
use HughCube\Laravel\AliOSS\OSS;
use HughCube\Laravel\AliOSS\OssAdapter;

class OSSTest extends TestCase
{
    public function testExtendsAliOSS(): void
    {
        $this->assertTrue(is_subclass_of(OSS::class, AliOSS::class));
    }

    public function testGetClient(): void
    {
        $this->assertInstanceOf(OssAdapter::class, OSS::getClient('oss'));
    }

    public function testWatermarkText(): void
    {
        $text = 'Test';
        $this->assertSame(AliOSS::watermarkText($text), OSS::watermarkText($text));
    }
}
