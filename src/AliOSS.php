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
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use OSS\OssClient;
use RuntimeException;

/**
 * @method static null|string getBucket()
 * @method static null|string getCdnBaseUrl()
 * @method static null|string getPrefix()
 * @method static null|string makePath()
 * @method static null|string getDefaultAcl()
 * @method static bool fileExists(string $path)
 * @method static void write(string $path, string $contents, Config $config = null)
 * @method static void writeStream(string $path, $contents, Config $config = null)
 * @method static string read(string $path)
 * @method static string readStream(string $path)
 * @method static void delete(string $path)
 * @method static void deleteDirectory(string $path)
 * @method static void createDirectory(string $path, Config $config = null)
 * @method static void setVisibility(string $path, string $visibility)
 * @method static FileAttributes visibility(string $path)
 * @method static FileAttributes mimeType(string $path)
 * @method static FileAttributes lastModified(string $path)
 * @method static FileAttributes fileSize(string $path)
 * @method static void copy(string $source, string $destination, Config $config = null)
 * @method static void move(string $source, string $destination, Config $config = null)
 * @method static FileAttributes getFileAttributes(string $path)
 * @method static null|string cdnUrl(string $path)
 * @method static string url(string $path)
 * @method static string authUrl($path, $timeout = 60, $method = OssClient::OSS_HTTP_GET, Config $config = null)
 * @method static void putUrl($url, $path, Config $config = null)
 * @method static string putUrlAndReturnUrl($url, $path, Config $config = null)
 * @method static null|string putUrlIfChangeUrl(mixed $cfile, mixed $dfile, string $prefix = '')
 * @method static void putFile($file, string $path, Config $config = null)
 * @method static string putFileAndReturnUrl($file, string $path, Config $config = null)
 * @method static void download($path, $file)
 */
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

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param string $method
     * @param array  $args
     *
     * @throws RuntimeException
     *
     * @return mixed
     */
    public static function __callStatic(string $method, array $args = [])
    {
        return static::getClient()->$method(...$args);
    }
}
