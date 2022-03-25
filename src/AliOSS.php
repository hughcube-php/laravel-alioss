<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/3/23
 * Time: 23:00.
 */

namespace HughCube\Laravel\AliOSS;

use BadMethodCallException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class AliOSS
{
    public static function getClient(string $name = 'alioss'): OssAdapter
    {
        $disk = Storage::disk($name);

        $adapter = $disk instanceof FilesystemAdapter ? $disk->getAdapter() : null;
        if (!$adapter instanceof OssAdapter) {
            throw new BadMethodCallException('Can only be called to alioss drives!');
        }

        return $adapter;
    }
}
