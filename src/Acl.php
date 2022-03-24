<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/3/24
 * Time: 18:21
 */

namespace HughCube\Laravel\AliOSS;

use League\Flysystem\Visibility;
use OSS\OssClient;

class Acl
{
    const OSS_ACL_TYPE_PUBLIC_READ = OssClient::OSS_ACL_TYPE_PUBLIC_READ;
    const OSS_ACL_TYPE_PUBLIC_READ_WRITE = OssClient::OSS_ACL_TYPE_PUBLIC_READ_WRITE;
    const OSS_ACL_TYPE_PRIVATE = OssClient::OSS_ACL_TYPE_PRIVATE;

    public static function getAclMap(): array
    {
        return [
            OssClient::OSS_ACL_TYPE_PUBLIC_READ => Visibility::PUBLIC,
            OssClient::OSS_ACL_TYPE_PUBLIC_READ_WRITE => Visibility::PUBLIC,
            OssClient::OSS_ACL_TYPE_PRIVATE => Visibility::PRIVATE,
        ];
    }

    public static function toAcl($visibility)
    {
        foreach (static::getAclMap() as $a => $v) {
            if ($visibility === $v) {
                return $a;
            }
        }
        return static::OSS_ACL_TYPE_PRIVATE;
    }

    public static function toVisibility($acl)
    {
        foreach (static::getAclMap() as $a => $v) {
            if ($acl === $a) {
                return $v;
            }
        }
        return Visibility::PRIVATE;
    }
}
