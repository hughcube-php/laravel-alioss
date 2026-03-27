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

    public static function toAcl($visibility): string
    {
        foreach (static::getAclMap() as $a => $v) {
            if ($visibility === $v) {
                return $a;
            }
        }

        return self::OSS_ACL_TYPE_PRIVATE;
    }

    public static function toVisibility($acl): string
    {
        foreach (static::getAclMap() as $a => $v) {
            if ($acl === $a) {
                return $v;
            }
        }

        return Visibility::PRIVATE;
    }
}
