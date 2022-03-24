<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/3/23
 * Time: 23:00
 */

namespace HughCube\Laravel\AliOSS;

use BadMethodCallException;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use OSS\Core\OssException;
use OSS\OssClient;

class OssAdapter implements FilesystemAdapter
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var OssClient
     */
    protected $ossClient;

    /**
     * @var PathPrefixer
     */
    private $prefixer;

    /**
     * @param  array  $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        $this->prefixer = new PathPrefixer(($this->config['prefix'] ?? ''), '/');
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
        $path = $this->prefixer->prefixPath($path);

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
        $path = $this->prefixer->prefixPath($path);

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
        $path = $this->prefixer->prefixPath($path);

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
        $path = $this->prefixer->prefixPath($path);

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
        $path = $this->prefixer->prefixPath($path);

        $this->getOssClient()->createObjectDir($this->getBucket(), $path, $config->get('options'));
    }

    /**
     * @inheritDoc
     * @throws OssException
     */
    public function setVisibility(string $path, string $visibility): void
    {
        $path = $this->prefixer->prefixPath($path);

        $this->getOssClient()->putObjectAcl($this->getBucket(), $path, Acl::toAcl($visibility));
    }

    /**
     * @inheritDoc
     * @throws OssException
     */
    public function visibility(string $path): FileAttributes
    {
        $path = $this->prefixer->prefixPath($path);

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
            $this->prefixer->prefixPath($source),
            $this->getBucket(),
            $this->prefixer->prefixPath($destination),
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
        $path = $this->prefixer->prefixPath($path);
        $meta = $this->getOssClient()->getObjectMeta($this->getBucket(), $path);

        return new FileAttributes(
            $path,
            $meta['content-length'],
            null,
            $meta['info']['filetime'],
            $meta['content-type']
        );
    }
}
