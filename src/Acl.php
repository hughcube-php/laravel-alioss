<?php

namespace HughCube\Laravel\AliOSS;

use League\Flysystem\Visibility;

class Acl
{
    const OSS_ACL_TYPE_PUBLIC_READ = 'public-read';
    const OSS_ACL_TYPE_PUBLIC_READ_WRITE = 'public-read-write';
    const OSS_ACL_TYPE_PRIVATE = 'private';
    const OSS_ACL_TYPE_DEFAULT = 'default';

    public static function getAclMap(): array
    {
        return [
            self::OSS_ACL_TYPE_PUBLIC_READ       => Visibility::PUBLIC,
            self::OSS_ACL_TYPE_PUBLIC_READ_WRITE => Visibility::PUBLIC,
            self::OSS_ACL_TYPE_PRIVATE           => Visibility::PRIVATE,
        ];
    }

    /**
     * Visibility 转 ACL。
     *
     * 注意：Flysystem 只有 PUBLIC/PRIVATE 两种可见性，
     * PUBLIC 始终映射为 public-read（而非 public-read-write），存在有损转换。
     */
    public static function toAcl(string $visibility): string
    {
        foreach (static::getAclMap() as $a => $v) {
            if ($visibility === $v) {
                return $a;
            }
        }

        return self::OSS_ACL_TYPE_PRIVATE;
    }

    public static function toVisibility(string $acl): string
    {
        foreach (static::getAclMap() as $a => $v) {
            if ($acl === $a) {
                return $v;
            }
        }

        return Visibility::PRIVATE;
    }
}
