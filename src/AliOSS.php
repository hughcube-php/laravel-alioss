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
use League\Flysystem\Config as Options;
use League\Flysystem\FileAttributes;
use League\Flysystem\PathPrefixer;
use OSS\OssClient;
use RuntimeException;

/**
 * @method static Options forbidOverwriteOptions()
 * @method static OssAdapter withConfig()
 * @method static OssAdapter withBucket(string $bucket)
 * @method static PathPrefixer getPrefixer()
 * @method static string getBucket()
 * @method static null|string getCdnBaseUrl()
 * @method static string makePath(string $path, Options $options = null)
 * @method static string getDefaultAcl()
 * @method static bool fileExists(string $path, Options $options = null)
 * @method static bool directoryExists(string $path, Options $options = null)
 * @method static void write(string $path, string $contents, Options $options = null)
 * @method static void writeStream(string $path, resource $contents, Options $options = null)
 * @method static string read(string $path, Options $options = null)
 * @method static resource readStream(string $path, Options $options = null)
 * @method static void delete(string $path, Options $options = null)
 * @method static void deleteDirectory(string $path, Options $options = null)
 * @method static void createDirectory(string $path, Options $options = null)
 * @method static void setVisibility(string $path, string $visibility, Options $options = null)
 * @method static FileAttributes visibility(string $path, Options $options = null)
 * @method static FileAttributes mimeType(string $path, Options $options = null)
 * @method static FileAttributes lastModified(string $path, Options $options = null)
 * @method static FileAttributes fileSize(string $path, Options $options = null)
 * @method static void copy(string $source, string $destination, Options $options = null)
 * @method static void move(string $source, string $destination, Options $options = null)
 * @method static FileAttributes getFileAttributes(string $path, Options $options = null)
 * @method static null|string cdnUrl(string $path, Options $options = null)
 * @method static string url(string $path, Options $options = null)
 * @method static string authUrl($path, $timeout = 60, $method = OssClient::OSS_HTTP_GET, Options $options = null)
 * @method static void putUrl($url, $path, Options $options = null)
 * @method static string putUrlAndReturnUrl($url, $path, Options $options = null)
 * @method static string|null putUrlIfChangeUrl(mixed $cfile, mixed $dfile, string $prefix = '', Options $options = null)
 * @method static void putFile($file, string $path, Options $options = null)
 * @method static string putFileAndReturnUrl($file, string $path, Options $options = null)
 * @method static void download($path, $file, Options $options = null)
 *
 * @see OssAdapter
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
     * @param  string  $method
     * @param  array  $args
     *
     * @return mixed
     * @throws RuntimeException
     *
     */
    public static function __callStatic(string $method, array $args = [])
    {
        return static::getClient()->$method(...$args);
    }
}
