<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/3/23
 * Time: 23:00
 */

namespace HughCube\Laravel\AliOSS;

use BadMethodCallException;
use GuzzleHttp\Exception\GuzzleException;
use HughCube\GuzzleHttp\HttpClientTrait;
use HughCube\PUrl\HUrl;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use OSS\Core\OssException;
use OSS\OssClient;

class OssAdapter implements FilesystemAdapter
{
    use HttpClientTrait;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var OssClient
     */
    protected $ossClient;

    /**
     * @param  array  $config
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

    /**
     * @throws OssException
     */
    public function getDefaultAcl()
    {
        return $this->config['acl'] ?? $this->getBucketAcl();
    }

    /**
     * @throws OssException
     */
    public function getBucketAcl(): string
    {
        return $this->getOssClient()->getBucketAcl($this->getBucket());
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
    public function write(string $path, string $contents, Config $config): void
    {
        $this->getOssClient()->putObject($this->getBucket(), $path, $contents, $config->get('options'));
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
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
        return;
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $path, Config $config): void
    {
        $this->getOssClient()->createObjectDir($this->getBucket(), $path, $config->get('options'));
    }

    /**
     * @inheritDoc
     * @throws OssException
     */
    public function setVisibility(string $path, string $visibility): void
    {
        $this->getOssClient()->putObjectAcl($this->getBucket(), $path, Acl::toAcl($visibility));
    }

    /**
     * @inheritDoc
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
     * @throws OssException
     */
    public function copy(string $source, string $destination, Config $config): void
    {
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
     * @throws OssException
     */
    public function move(string $source, string $destination, Config $config): void
    {
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

    /**
     * @throws OssException
     */
    public function url($path): string
    {
        return HUrl::instance($this->signUrl($path))->withQueryArray([])->toString();
    }

    /**
     * @throws OssException
     */
    public function signUrl($path, $timeout = 60, $method = OssClient::OSS_HTTP_GET, Config $config = null): string
    {
        $config = $config ?? new Config();

        $url = HUrl::parse($path);
        $path = $url instanceof HUrl ? $url->getPath() : $path;

        $signUrl = $this->getOssClient()->signUrl(
            $this->getBucket(),
            $path,
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
     * @throws FilesystemException
     */
    public function putFile($file, string $path, Config $config = null)
    {
        $config = $config ?? new Config();
        $this->write($path, file_get_contents($file), $config);
    }

    public function download($path, $file)
    {
        $this->getOssClient()->getObject($this->getBucket(), $path, [
            OssClient::OSS_FILE_DOWNLOAD => $file
        ]);
    }
}
