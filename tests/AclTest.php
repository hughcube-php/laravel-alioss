<?php

namespace HughCube\Laravel\AliOSS\Tests;

use HughCube\Laravel\AliOSS\Acl;
use League\Flysystem\Visibility;
use PHPUnit\Framework\Attributes\DataProvider;

class AclTest extends TestCase
{
    public function testConstants(): void
    {
        $this->assertSame('public-read', Acl::OSS_ACL_TYPE_PUBLIC_READ);
        $this->assertSame('public-read-write', Acl::OSS_ACL_TYPE_PUBLIC_READ_WRITE);
        $this->assertSame('private', Acl::OSS_ACL_TYPE_PRIVATE);
        $this->assertSame('default', Acl::OSS_ACL_TYPE_DEFAULT);
    }

    public function testGetAclMap(): void
    {
        $map = Acl::getAclMap();
        $this->assertIsArray($map);
        $this->assertSame(Visibility::PUBLIC, $map['public-read']);
        $this->assertSame(Visibility::PUBLIC, $map['public-read-write']);
        $this->assertSame(Visibility::PRIVATE, $map['private']);
    }

    #[DataProvider('toAclProvider')]
    public function testToAcl(string $visibility, string $expected): void
    {
        $this->assertSame($expected, Acl::toAcl($visibility));
    }

    public static function toAclProvider(): array
    {
        return [
            'public' => [Visibility::PUBLIC, 'public-read'],
            'private' => [Visibility::PRIVATE, 'private'],
            'unknown' => ['unknown', 'private'],
        ];
    }

    #[DataProvider('toVisibilityProvider')]
    public function testToVisibility(string $acl, string $expected): void
    {
        $this->assertSame($expected, Acl::toVisibility($acl));
    }

    public static function toVisibilityProvider(): array
    {
        return [
            'public read' => ['public-read', Visibility::PUBLIC],
            'public read write' => ['public-read-write', Visibility::PUBLIC],
            'private' => ['private', Visibility::PRIVATE],
            'unknown' => ['unknown', Visibility::PRIVATE],
        ];
    }
}
