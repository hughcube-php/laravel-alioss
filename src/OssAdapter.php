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
use JetBrains\PhpStorm\Pure;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use OSS\Core\OssException;
use OSS\OssClient;

/**
 * @mixin OssClient
 */
class OssAdapter implements FilesystemAdapter
{
    use HttpClientTrait;

    private array $config;

    private null|OssClient $ossClient = null;

    private null|PathPrefixer $prefixer = null;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    #[Pure]
    public function forbidOverwritePutConfig(): Config
    {
        return new Config([
            'options' => [
                OssClient::OSS_HEADERS => [
                    'x-oss-forbid-overwrite' => 'true',
                ],
            ],
        ]);
    }

    #[Pure]
    public function withConfig(array $config = []): static
    {
        /** @phpstan-ignore-next-line */
        return new static(array_merge($this->config, $config));
    }

    #[Pure]
    public function withBucket(string $bucket): static
    {
        return $this->withConfig(['bucket' => $bucket]);
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

    public function getPrefixer(): PathPrefixer
    {
        if (!$this->prefixer instanceof PathPrefixer) {
            $this->prefixer = new PathPrefixer((($this->config['prefix'] ?? '') ?: ''), DIRECTORY_SEPARATOR);
        }

        return $this->prefixer;
    }

    public function getBucket()
    {
        return $this->config['bucket'];
    }

    public function getCdnBaseUrl()
    {
        return ($this->config['cdnBaseUrl'] ?? null) ?: null;
    }

    public function makePath(string $path, Config $config = null): string
    {
        if (!$config instanceof Config || null === $config->get('with_prefix') || $config->get('with_prefix')) {
            return $this->getPrefixer()->prefixPath($path);
        }

        return $path;
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
    public function fileExists(string $path, Config $config = null): bool
    {
        $config = $config ?? new Config();

        return $this->getOssClient()->doesObjectExist(
            $this->getBucket(),
            ltrim($this->makePath($path, $config), '/'),
            $config->get('options')
        );
    }

    /**
     * @inheritDoc
     */
    public function directoryExists(string $path, Config $config = null): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function write(string $path, string $contents, Config $config = null): void
    {
        $config = $config ?? new Config();

        $this->getOssClient()->putObject(
            $this->getBucket(),
            ltrim($this->makePath($path, $config), '/'),
            $contents,
            $config->get('options')
        );
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $path, $contents, Config $config = null): void
    {
        $config = $config ?? new Config();

        $this->getOssClient()->putObject(
            $this->getBucket(),
            ltrim($this->makePath($path, $config), '/'),
            stream_get_contents($contents),
            $config->get('options')
        );
    }

    /**
     * @inheritDoc
     */
    public function read(string $path, Config $config = null): string
    {
        $config = $config ?? new Config();

        return $this->getOssClient()->getObject(
            $this->getBucket(),
            ltrim($this->makePath($path, $config), '/'),
            $config->get('options')
        );
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $path, Config $config = null)
    {
        $config = $config ?? new Config();

        $contents = $this->getOssClient()->getObject(
            $this->getBucket(),
            ltrim($this->makePath($path, $config), '/'),
            $config->get('options')
        );

        /** @var resource $stream */
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $path, Config $config = null): void
    {
        $config = $config ?? new Config();

        $this->getOssClient()->deleteObject(
            $this->getBucket(),
            ltrim($this->makePath($path, $config), '/'),
            $config->get('options')
        );
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $path, Config $config = null): void
    {
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $path, Config $config = null): void
    {
        $config = $config ?? new Config();

        $this->getOssClient()->createObjectDir(
            $this->getBucket(),
            ltrim($this->makePath($path, $config), '/'),
            $config->get('options')
        );
    }

    /**
     * @inheritDoc
     *
     * @throws OssException
     */
    public function setVisibility(string $path, string $visibility, Config $config = null): void
    {
        $config = $config ?? new Config();

        $this->getOssClient()->putObjectAcl(
            $this->getBucket(),
            ltrim($this->makePath($path, $config), '/'),
            Acl::toAcl($visibility),
            $config->get('options')
        );
    }

    /**
     * @inheritDoc
     *
     * @throws OssException
     */
    public function visibility(string $path, Config $config = null): FileAttributes
    {
        $config = $config ?? new Config();

        $acl = $this->getOssClient()->getObjectAcl(
            $this->getBucket(),
            ltrim($this->makePath($path, $config), '/'),
            $config->get('options')
        );

        $acl = 'default' === $acl ? $this->getDefaultAcl() : $acl;

        return new FileAttributes($path, null, Acl::toVisibility($acl));
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path, Config $config = null): FileAttributes
    {
        return $this->getFileAttributes($path, $config);
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path, Config $config = null): FileAttributes
    {
        return $this->getFileAttributes($path, $config);
    }

    /**
     * @inheritDoc
     */
    public function fileSize(string $path, Config $config = null): FileAttributes
    {
        return $this->getFileAttributes($path, $config);
    }

    /**
     * @inheritDoc
     */
    public function listContents(string $path, bool $deep, Config $config = null): iterable
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
            ltrim($this->makePath($source, $config), '/'),
            $this->getBucket(),
            ltrim($this->makePath($destination, $config), '/'),
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

        $this->getOssClient()->copyObject(
            $this->getBucket(),
            ltrim($this->makePath($source, $config), '/'),
            $this->getBucket(),
            ltrim($this->makePath($destination, $config), '/'),
            $config->get('options')
        );

        $this->getOssClient()->deleteObject(
            $this->getBucket(),
            ltrim($this->makePath($source, $config), '/'),
            $config->get('options')
        );
    }

    public function getFileAttributes($path, Config $config = null): FileAttributes
    {
        $config = $config ?? new Config();

        $meta = $this->getOssClient()->getObjectMeta(
            $this->getBucket(),
            ltrim($this->makePath($path, $config), '/'),
            $config->get('options')
        );

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
    public function authUrl($path, $timeout = 60, $method = OssClient::OSS_HTTP_GET, Config $config = null): string
    {
        $config = $config ?? new Config();

        $url = HUrl::parse($path);
        $path = $url instanceof HUrl ? $url->getPath() : $this->makePath($path, $config);

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

    public function cdnUrl($path, Config $config = null): null|string
    {
        if (empty($this->getCdnBaseUrl())) {
            return null;
        }

        if (!($url = HUrl::parse($path)) instanceof HUrl) {
            return sprintf(
                '%s/%s',
                rtrim($this->getCdnBaseUrl(), '/'),
                ltrim($this->makePath($path, $config), '/')
            );
        }

        $baseUrl = HUrl::instance($this->getCdnBaseUrl());

        return $url->withHost($baseUrl->getHost())->withScheme($baseUrl->getScheme())->toString();
    }

    /**
     * @throws OssException
     */
    public function url($path, Config $config = null): string
    {
        $url = $this->authUrl($path, 60, OssClient::OSS_HTTP_GET, $config);

        return HUrl::instance($url)->withQueryArray([])->toString();
    }

    /**
     * @throws GuzzleException
     */
    public function putUrl($url, $path, Config $config = null)
    {
        $config = $config ?? new Config();
        $response = $this->getHttpClient()->get($url, $config->get('http', []));

        $this->getOssClient()->putObject(
            $this->getBucket(),
            ltrim($this->makePath($path, $config), '/'),
            $response->getBody()->getContents(),
            $config->get('options')
        );
    }

    /**
     * @throws GuzzleException
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
     * @param mixed       $cfile  需要上传的url
     * @param mixed       $dfile  db的url
     * @param string      $prefix
     * @param Config|null $config
     *
     * @throws OssException
     * @throws GuzzleException
     *
     * @return string|null
     */
    public function putUrlIfChangeUrl(
        mixed $cfile,
        mixed $dfile,
        string $prefix = '',
        Config $config = null
    ): null|string {
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

        return $this->putUrlAndReturnUrl($cfile, $path, $config);
    }

    public function putFile($file, string $path, Config $config = null)
    {
        $config = $config ?? new Config();

        $this->getOssClient()->putObject(
            $this->getBucket(),
            ltrim($this->makePath($path, $config), '/'),
            file_get_contents($file),
            $config->get('options')
        );
    }

    /**
     * @throws OssException
     */
    public function putFileAndReturnUrl($file, string $path, Config $config = null): string
    {
        $this->putFile($file, $path, $config);

        return $this->cdnUrl($path, $config) ?: $this->url($path, $config);
    }

    public function download($path, $file, Config $config = null)
    {
        $config = $config ?? new Config();

        $this->getOssClient()->getObject(
            $this->getBucket(),
            ltrim($this->makePath($path, $config), '/'),
            array_merge($config->get('options', []), [OssClient::OSS_FILE_DOWNLOAD => $file])
        );
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
