<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/3/23
 * Time: 23:00.
 */

namespace HughCube\Laravel\AliOSS;

use BadMethodCallException;
use GuzzleHttp\Exception\GuzzleException;
use HughCube\GuzzleHttp\HttpClientTrait;
use HughCube\PUrl\HUrl;
use HughCube\PUrl\Url;
use Illuminate\Support\Str;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use OSS\Core\OssException;
use OSS\OssClient;

/**
 * @mixin OssClient
 */
class OssAdapter implements FilesystemAdapter
{
    use HttpClientTrait;

    protected array $config;

    protected null|OssClient $ossClient = null;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @throws
     * @phpstan-ignore-next-line
     */
    public function getOssClient(): OssClient
    {
        if (!$this->ossClient instanceof OssClient) {
            $this->ossClient = new OssClient(
                $this->config['accessKeyId'],
                $this->config['accessKeySecret'],
                $this->config['endpoint'],
                (($this->config['isCName'] ?? false) ?: false),
                (($this->config['securityToken'] ?? null) ?: null),
                (($this->config['requestProxy'] ?? null) ?: null)
            );
        }

        return $this->ossClient;
    }

    public function getBucket()
    {
        return $this->config['bucket'];
    }

    public function getCdnBaseUrl()
    {
        return ($this->config['cdnBaseUrl'] ?? null) ?: null;
    }

    public function getPrefix()
    {
        return ($this->config['prefix'] ?? null) ?: null;
    }

    public function makePath(string $path): string
    {
        return trim(sprintf('/%s/%s', trim($this->getPrefix()), ltrim($path)));
    }

    /**
     * @throws OssException
     */
    public function getDefaultAcl()
    {
        return $this->config['acl'] ?? $this->getOssClient()->getBucketAcl($this->getBucket());
    }

    /**
     * @inheritDoc
     */
    public function fileExists(string $path): bool
    {
        return $this->getOssClient()->doesObjectExist($this->getBucket(), $path);
    }

    /**
     * @inheritDoc
     */
    public function directoryExists(string $path): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function write(string $path, string $contents, Config $config = null): void
    {
        $config = $config ?? new Config();
        $this->getOssClient()->putObject($this->getBucket(), $path, $contents, $config->get('options'));
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $path, $contents, Config $config = null): void
    {
        $config = $config ?? new Config();
        $this->write($path, stream_get_contents($contents), $config);
    }

    /**
     * @inheritDoc
     */
    public function read(string $path): string
    {
        return $this->getOssClient()->getObject($this->getBucket(), $path);
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $path)
    {
        /** @var resource $stream */
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $path): void
    {
        $this->getOssClient()->deleteObject($this->getBucket(), $path);
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $path): void
    {
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $path, Config $config = null): void
    {
        $config = $config ?? new Config();
        $this->getOssClient()->createObjectDir($this->getBucket(), $path, $config->get('options'));
    }

    /**
     * @inheritDoc
     *
     * @throws OssException
     */
    public function setVisibility(string $path, string $visibility): void
    {
        $this->getOssClient()->putObjectAcl($this->getBucket(), $path, Acl::toAcl($visibility));
    }

    /**
     * @inheritDoc
     *
     * @throws OssException
     */
    public function visibility(string $path): FileAttributes
    {
        $acl = $this->getOssClient()->getObjectAcl($this->getBucket(), $path);
        $acl = 'default' === $acl ? $this->getDefaultAcl() : $acl;

        return new FileAttributes($path, null, Acl::toVisibility($acl));
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): FileAttributes
    {
        return $this->getFileAttributes($path);
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): FileAttributes
    {
        return $this->getFileAttributes($path);
    }

    /**
     * @inheritDoc
     */
    public function fileSize(string $path): FileAttributes
    {
        return $this->getFileAttributes($path);
    }

    /**
     * @inheritDoc
     */
    public function listContents(string $path, bool $deep): iterable
    {
        throw new BadMethodCallException();
    }

    /**
     * @inheritDoc
     *
     * @throws OssException
     */
    public function copy(string $source, string $destination, Config $config = null): void
    {
        $config = $config ?? new Config();
        $this->getOssClient()->copyObject(
            $this->getBucket(),
            $source,
            $this->getBucket(),
            $destination,
            $config->get('options')
        );
    }

    /**
     * @inheritDoc
     *
     * @throws OssException
     */
    public function move(string $source, string $destination, Config $config = null): void
    {
        $config = $config ?? new Config();
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function getFileAttributes($path): FileAttributes
    {
        $meta = $this->getOssClient()->getObjectMeta($this->getBucket(), $path);

        return new FileAttributes(
            $path,
            $meta['content-length'],
            null,
            $meta['info']['filetime'],
            $meta['content-type']
        );
    }

    public function cdnUrl($path): null|string
    {
        if (empty($this->getCdnBaseUrl())) {
            return null;
        }

        $url = HUrl::parse($path);
        if (!$url instanceof HUrl) {
            return sprintf('%s/%s', rtrim($this->getCdnBaseUrl(), '/'), ltrim($path, '/'));
        }

        $baseUrl = HUrl::instance($this->getCdnBaseUrl());

        return $url->withHost($baseUrl->getHost())->withScheme($baseUrl->getScheme())->toString();
    }

    /**
     * @throws OssException
     */
    public function url($path): string
    {
        return HUrl::instance($this->authUrl($path))->withQueryArray([])->toString();
    }

    /**
     * @throws OssException
     */
    public function authUrl($path, $timeout = 60, $method = OssClient::OSS_HTTP_GET, Config $config = null): string
    {
        $config = $config ?? new Config();

        $url = HUrl::parse($path);
        $path = $url instanceof HUrl ? $url->getPath() : $path;

        $signUrl = $this->getOssClient()->signUrl(
            $this->getBucket(),
            ltrim($path, '/'),
            $timeout,
            $method,
            $config->get('options')
        );
        if (!$url instanceof HUrl) {
            return $signUrl;
        }

        foreach (HUrl::instance($signUrl)->getQueryArray() as $name => $value) {
            $url = $url->withQueryValue($name, $value);
        }

        return $url->toString();
    }

    /**
     * @throws GuzzleException
     * @throws FilesystemException
     */
    public function putUrl($url, $path, Config $config = null)
    {
        $config = $config ?? new Config();
        $response = $this->getHttpClient()->get($url, $config->get('http', []));
        $this->write($path, $response->getBody()->getContents(), $config);
    }

    /**
     * @throws GuzzleException
     * @throws FilesystemException
     * @throws OssException
     */
    public function putUrlAndReturnUrl($url, $path, Config $config = null): string
    {
        $this->putUrl($url, $path, $config);

        return $this->cdnUrl($path) ?: $this->url($path);
    }

    /**
     * 一般用于保存用户微信头像到DB的场景, 如果文件未发生变化不上传(仅通过url判断).
     *
     * @param mixed  $cfile  需要上传的url
     * @param mixed  $dfile  db的url
     * @param string $prefix
     *
     * @throws FilesystemException
     * @throws GuzzleException
     * @throws OssException
     *
     * @return string|null
     */
    public function putUrlIfChangeUrl(mixed $cfile, mixed $dfile, string $prefix = ''): null|string
    {
        $cUrl = empty($cfile) ? null : Url::parse($cfile);
        $dUrl = empty($dfile) ? null : Url::parse($dfile);

        /** 需要上传的文件不存在(微信头像为空) */
        if (!$cUrl instanceof Url) {
            return $dfile instanceof Url ? $dfile->toString() : null;
        }

        /** db里面的文件路径包含需要上传的文件(微信头像为空), 说明已经上传了无需更改 */
        if ($dUrl instanceof Url && Str::contains($dUrl->getPath(), $cUrl->getPath())) {
            return $dfile->toString();
        }

        $path = trim(sprintf('/%s/%s', trim($prefix, '/'), trim($cUrl->getPath(), '/')), '/');

        return $this->putUrlAndReturnUrl($cfile, trim($path, '/'));
    }

    /**
     * @throws FilesystemException
     */
    public function putFile($file, string $path, Config $config = null)
    {
        $config = $config ?? new Config();
        $this->write($path, file_get_contents($file), $config);
    }

    /**
     * @throws FilesystemException
     * @throws OssException
     */
    public function putFileAndReturnUrl($file, string $path, Config $config = null): string
    {
        $this->putFile($file, $path, $config);

        return $this->cdnUrl($path) ?: $this->url($path);
    }

    public function download($path, $file)
    {
        $this->getOssClient()->getObject($this->getBucket(), $path, [OssClient::OSS_FILE_DOWNLOAD => $file]);
    }

    /**
     * Pass dynamic methods call onto oss.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws BadMethodCallException
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->getOssClient()->{$method}(...$parameters);
    }
}
