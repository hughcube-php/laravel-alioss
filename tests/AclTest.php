<?php

namespace HughCube\Laravel\AliOSS\Tests;

use HughCube\Laravel\AliOSS\Acl;
use League\Flysystem\Visibility;
use OSS\OssClient;
use PHPUnit\Framework\Attributes\DataProvider;

class AclTest extends TestCase
{
    public function testConstants(): void
    {
        $this->assertSame(OssClient::OSS_ACL_TYPE_PUBLIC_READ, Acl::OSS_ACL_TYPE_PUBLIC_READ);
        $this->assertSame(OssClient::OSS_ACL_TYPE_PUBLIC_READ_WRITE, Acl::OSS_ACL_TYPE_PUBLIC_READ_WRITE);
        $this->assertSame(OssClient::OSS_ACL_TYPE_PRIVATE, Acl::OSS_ACL_TYPE_PRIVATE);
    }

    public function testGetAclMap(): void
    {
        $map = Acl::getAclMap();

        $this->assertIsArray($map);
        $this->assertArrayHasKey(OssClient::OSS_ACL_TYPE_PUBLIC_READ, $map);
        $this->assertArrayHasKey(OssClient::OSS_ACL_TYPE_PUBLIC_READ_WRITE, $map);
        $this->assertArrayHasKey(OssClient::OSS_ACL_TYPE_PRIVATE, $map);

        $this->assertSame(Visibility::PUBLIC, $map[OssClient::OSS_ACL_TYPE_PUBLIC_READ]);
        $this->assertSame(Visibility::PUBLIC, $map[OssClient::OSS_ACL_TYPE_PUBLIC_READ_WRITE]);
        $this->assertSame(Visibility::PRIVATE, $map[OssClient::OSS_ACL_TYPE_PRIVATE]);
    }

    #[DataProvider('toAclProvider')]
    public function testToAcl(string $visibility, int|string $expectedAcl): void
    {
        $this->assertSame($expectedAcl, Acl::toAcl($visibility));
    }

    public static function toAclProvider(): array
    {
        return [
            'public' => [Visibility::PUBLIC, OssClient::OSS_ACL_TYPE_PUBLIC_READ],
            'private' => [Visibility::PRIVATE, OssClient::OSS_ACL_TYPE_PRIVATE],
            'unknown' => ['unknown', Acl::OSS_ACL_TYPE_PRIVATE],
        ];
    }

    #[DataProvider('toVisibilityProvider')]
    public function testToVisibility(int|string $acl, string $expectedVisibility): void
    {
        $this->assertSame($expectedVisibility, Acl::toVisibility($acl));
    }

    public static function toVisibilityProvider(): array
    {
        return [
            'public read' => [OssClient::OSS_ACL_TYPE_PUBLIC_READ, Visibility::PUBLIC],
            'public read write' => [OssClient::OSS_ACL_TYPE_PUBLIC_READ_WRITE, Visibility::PUBLIC],
            'private' => [OssClient::OSS_ACL_TYPE_PRIVATE, Visibility::PRIVATE],
            'unknown' => ['unknown', Visibility::PRIVATE],
        ];
    }
}
