<?php

namespace HughCube\Laravel\AliOSS;

use BadMethodCallException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class AliOSS
{
    public static function getClient(?string $name = null): OssAdapter
    {
        $disk = Storage::disk($name ?: 'oss');

        $adapter = $disk instanceof FilesystemAdapter ? $disk->getAdapter() : null;
        if (!$adapter instanceof OssAdapter) {
            throw new BadMethodCallException('Can only be called to alioss drives!');
        }

        return $adapter;
    }

    public static function watermarkText(string $text): string
    {
        return OssAdapter::watermarkText($text);
    }
}
