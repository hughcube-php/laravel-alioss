<?php

namespace HughCube\Laravel\AliOSS;

use AlibabaCloud\Oss\V2 as Oss;
use BadMethodCallException;
use GuzzleHttp\Exception\GuzzleException;
use HughCube\GuzzleHttp\HttpClientTrait;
use HughCube\PUrl\HUrl;
use HughCube\PUrl\Url;
use Illuminate\Support\Str;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;

class OssAdapter implements FilesystemAdapter
{
    use HttpClientTrait;

    public const DOMAIN_CDN = 'cdn';
    public const DOMAIN_UPLOAD = 'upload';
    public const DOMAIN_OSS = 'oss';
    public const DOMAIN_OSS_INTERNAL = 'oss_internal';

    private array $config;
    private ?Oss\Client $ossClient = null;
    private ?PathPrefixer $prefixer = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    // ==================== 配置 ====================

    public function client(): Oss\Client
    {
        if ($this->ossClient === null) {
            $credentialsProvider = new Oss\Credentials\StaticCredentialsProvider(
                $this->config['accessKeyId'],
                $this->config['accessKeySecret'],
                ($this->config['securityToken'] ?? null) ?: null
            );

            $cfg = Oss\Config::loadDefault();
            $cfg->setCredentialsProvider($credentialsProvider);
            $cfg->setRegion($this->region() ?? 'cn-hangzhou');

            $endpoint = $this->config['endpoint'] ?? null;
            if (!empty($endpoint)) {
                $cfg->setEndpoint($endpoint);
            }

            if (!empty($this->config['internal'])) {
                $cfg->setUseInternalEndpoint(true);
            }

            if (!empty($this->config['isCName'])) {
                $cfg->setUseCname(true);
            }

            $proxy = ($this->config['requestProxy'] ?? null) ?: null;
            if (!empty($proxy)) {
                $cfg->setProxyHost($proxy);
            }

            $this->ossClient = new Oss\Client($cfg);
        }

        return $this->ossClient;
    }

    public function bucket(): string
    {
        return $this->config['bucket'];
    }

    public function region(): ?string
    {
        return ($this->config['region'] ?? null) ?: null;
    }

    public function accessKeyId(): string
    {
        return $this->config['accessKeyId'];
    }

    public function accessKeySecret(): string
    {
        return $this->config['accessKeySecret'];
    }

    public function prefixer(): PathPrefixer
    {
        if ($this->prefixer === null) {
            $this->prefixer = new PathPrefixer(($this->config['prefix'] ?? '') ?: '', '/');
        }

        return $this->prefixer;
    }

    public function cdnBaseUrl(): ?string
    {
        return ($this->config['cdnBaseUrl'] ?? null) ?: null;
    }

    public function uploadBaseUrl(): ?string
    {
        return ($this->config['uploadBaseUrl'] ?? null) ?: null;
    }

    public function withConfig(array $config = []): static
    {
        return new static(array_merge($this->config, $config));
    }

    public function withBucket(string $bucket): static
    {
        return $this->withConfig(['bucket' => $bucket]);
    }

    public function noOverwrite(): Config
    {
        return new Config(['forbidOverwrite' => true]);
    }

    // ==================== 域名 ====================

    public function cdnDomain(): ?string
    {
        $url = HUrl::parse($this->cdnBaseUrl());
        return $url instanceof HUrl ? $url->getHost() : null;
    }

    public function uploadDomain(): ?string
    {
        $url = HUrl::parse($this->uploadBaseUrl());
        return $url instanceof HUrl ? $url->getHost() : null;
    }

    public function ossDomain(): string
    {
        return sprintf('%s.oss-%s.aliyuncs.com', $this->bucket(), $this->region() ?? 'cn-hangzhou');
    }

    public function ossInternalDomain(): string
    {
        return sprintf('%s.oss-%s-internal.aliyuncs.com', $this->bucket(), $this->region() ?? 'cn-hangzhou');
    }

    // ==================== URL 解析 ====================

    /**
     * 解析任意 URL 为 OssUrl 对象
     */
    public function parseUrl($url): ?OssUrl
    {
        return OssUrl::tryFrom($this, $url);
    }

    // ==================== URL 构建 ====================

    public function url(string $path): OssUrl
    {
        return $this->ossUrl($path);
    }

    public function cdnUrl(string $path): ?OssUrl
    {
        if (empty($this->cdnBaseUrl())) {
            return null;
        }

        $raw = sprintf('%s/%s', rtrim($this->cdnBaseUrl(), '/'), $this->resolveKey($path));

        return OssUrl::from($this, $raw);
    }

    public function uploadUrl(string $path): ?OssUrl
    {
        if (empty($this->uploadBaseUrl())) {
            return null;
        }

        $raw = sprintf('%s/%s', rtrim($this->uploadBaseUrl(), '/'), $this->resolveKey($path));

        return OssUrl::from($this, $raw);
    }

    public function ossUrl(string $path): OssUrl
    {
        $raw = sprintf('https://%s/%s', $this->ossDomain(), $this->resolveKey($path));

        return OssUrl::from($this, $raw);
    }

    public function ossInternalUrl(string $path): OssUrl
    {
        $raw = sprintf('https://%s/%s', $this->ossInternalDomain(), $this->resolveKey($path));

        return OssUrl::from($this, $raw);
    }

    public function ossUri(string $path): string
    {
        return sprintf('oss://%s/%s', $this->bucket(), $this->resolveKey($path));
    }

    public function signUrl(string $path, int $timeout = 60): OssUrl
    {
        $presignResult = $this->client()->presign(
            new Oss\Models\GetObjectRequest(
                bucket: $this->bucket(),
                key: $this->resolveKey($path),
            ),
            ['expires' => new \DateInterval("PT{$timeout}S")]
        );

        return OssUrl::from($this, $presignResult->url);
    }

    public function signUploadUrl(string $path, int $timeout = 60): OssUrl
    {
        $presignResult = $this->client()->presign(
            new Oss\Models\PutObjectRequest(
                bucket: $this->bucket(),
                key: $this->resolveKey($path),
            ),
            ['expires' => new \DateInterval("PT{$timeout}S")]
        );

        return OssUrl::from($this, $presignResult->url);
    }

    public function presign(string $path, int $timeout = 60, string $method = 'GET'): Oss\Models\PresignResult
    {
        $request = match (strtoupper($method)) {
            'PUT'   => new Oss\Models\PutObjectRequest(bucket: $this->bucket(), key: $this->resolveKey($path)),
            'HEAD'  => new Oss\Models\HeadObjectRequest(bucket: $this->bucket(), key: $this->resolveKey($path)),
            default => new Oss\Models\GetObjectRequest(bucket: $this->bucket(), key: $this->resolveKey($path)),
        };

        return $this->client()->presign($request, ['expires' => new \DateInterval("PT{$timeout}S")]);
    }

    // ==================== URL 转换 ====================

    public function toCdnUrl(string $url): ?OssUrl
    {
        return $this->parseUrl($url)?->toCdn();
    }

    public function toUploadUrl(string $url): ?OssUrl
    {
        return $this->parseUrl($url)?->toUpload();
    }

    public function toOssUrl(string $url): ?OssUrl
    {
        return $this->parseUrl($url)?->toOss();
    }

    public function toOssInternalUrl(string $url): ?OssUrl
    {
        return $this->parseUrl($url)?->toOssInternal();
    }

    // ==================== URL 识别 ====================

    public function isCdnUrl(string $url): bool
    {
        return $this->matchUrlHost($url, $this->cdnDomain());
    }

    public function isUploadUrl(string $url): bool
    {
        return $this->matchUrlHost($url, $this->uploadDomain());
    }

    public function isOssUrl(string $url): bool
    {
        return $this->matchUrlHost($url, $this->ossDomain());
    }

    public function isOssInternalUrl(string $url): bool
    {
        return $this->matchUrlHost($url, $this->ossInternalDomain());
    }

    public function isBucketUrl(string $url): bool
    {
        return $this->isCdnUrl($url)
            || $this->isUploadUrl($url)
            || $this->isOssUrl($url)
            || $this->isOssInternalUrl($url);
    }

    // ==================== Flysystem FilesystemAdapter ====================

    public function fileExists(string $path): bool
    {
        return $this->client()->isObjectExist($this->bucket(), $this->resolveKey($path));
    }

    public function directoryExists(string $path): bool
    {
        return true;
    }

    public function write(string $path, string $contents, ?Config $config = null): void
    {
        $config = $config ?? new Config();
        $request = new Oss\Models\PutObjectRequest(
            bucket: $this->bucket(),
            key: $this->resolveKey($path),
            body: Oss\Utils::streamFor($contents),
        );

        if ($config->get('forbidOverwrite')) {
            $request->forbidOverwrite = true;
        }

        $this->client()->putObject($request);
    }

    public function writeStream(string $path, $contents, ?Config $config = null): void
    {
        $config = $config ?? new Config();
        $request = new Oss\Models\PutObjectRequest(
            bucket: $this->bucket(),
            key: $this->resolveKey($path),
            body: Oss\Utils::streamFor($contents),
        );

        if ($config->get('forbidOverwrite')) {
            $request->forbidOverwrite = true;
        }

        $this->client()->putObject($request);
    }

    public function read(string $path): string
    {
        $result = $this->client()->getObject(
            new Oss\Models\GetObjectRequest(bucket: $this->bucket(), key: $this->resolveKey($path))
        );

        return $result->body->getContents();
    }

    public function readStream(string $path)
    {
        $contents = $this->read($path);

        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $this->client()->deleteObject(
            new Oss\Models\DeleteObjectRequest(bucket: $this->bucket(), key: $this->resolveKey($path))
        );
    }

    public function deleteDirectory(string $path): void
    {
        throw new BadMethodCallException();
    }

    public function createDirectory(string $path, ?Config $config = null): void
    {
        $this->client()->putObject(
            new Oss\Models\PutObjectRequest(
                bucket: $this->bucket(),
                key: $this->resolveKey($path) . '/',
                body: Oss\Utils::streamFor(''),
                contentType: 'application/x-directory',
            )
        );
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->client()->putObjectAcl(
            new Oss\Models\PutObjectAclRequest(
                bucket: $this->bucket(),
                key: $this->resolveKey($path),
                acl: Acl::toAcl($visibility),
            )
        );
    }

    public function visibility(string $path): FileAttributes
    {
        $result = $this->client()->getObjectAcl(
            new Oss\Models\GetObjectAclRequest(bucket: $this->bucket(), key: $this->resolveKey($path))
        );

        $acl = $result->accessControlList->grant ?? 'default';
        if ($acl === 'default') {
            $acl = $this->defaultAcl();
        }

        return new FileAttributes($path, null, Acl::toVisibility($acl));
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->fileAttributes($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->fileAttributes($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->fileAttributes($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        throw new BadMethodCallException();
    }

    public function copy(string $source, string $destination, ?Config $config = null): void
    {
        $this->client()->copyObject(
            new Oss\Models\CopyObjectRequest(
                bucket: $this->bucket(),
                key: $this->resolveKey($destination),
                sourceBucket: $this->bucket(),
                sourceKey: $this->resolveKey($source),
            )
        );
    }

    public function move(string $source, string $destination, ?Config $config = null): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function fileAttributes(string $path): FileAttributes
    {
        $result = $this->client()->headObject(
            new Oss\Models\HeadObjectRequest(bucket: $this->bucket(), key: $this->resolveKey($path))
        );

        return new FileAttributes(
            $path,
            $result->contentLength ?? null,
            null,
            $result->lastModified?->getTimestamp(),
            $result->contentType ?? null
        );
    }

    // ==================== 扩展操作 ====================

    public function writeFile($file, string $path): OssUrl
    {
        $this->write($path, file_get_contents($file));

        return $this->cdnUrl($path) ?? $this->url($path);
    }

    /**
     * @throws GuzzleException
     */
    public function writeFromUrl(string $url, string $path): OssUrl
    {
        $response = $this->getHttpClient()->get($url, ['stream' => true]);

        $this->client()->putObject(
            new Oss\Models\PutObjectRequest(
                bucket: $this->bucket(),
                key: $this->resolveKey($path),
                body: $response->getBody(),
            )
        );

        return $this->cdnUrl($path) ?? $this->url($path);
    }

    /**
     * URL 变化时才上传（微信头像场景）
     */
    public function mirrorIfChanged(mixed $sourceUrl, mixed $existingUrl, string $prefix = ''): ?OssUrl
    {
        $source = Url::parse($sourceUrl);
        $existing = Url::parse($existingUrl);

        if (!$source instanceof Url) {
            return $existing instanceof Url ? OssUrl::from($this, $existing->toString()) : null;
        }

        if ($existing instanceof Url && Str::contains($existing->getPath(), $source->getPath())) {
            return OssUrl::from($this, $existing->toString());
        }

        $path = trim(sprintf('/%s/%s', trim($prefix, '/'), trim($source->getPath(), '/')), '/');

        return $this->writeFromUrl($sourceUrl, $path);
    }

    public function download(string $path, string $file): void
    {
        $this->client()->getObjectToFile(
            new Oss\Models\GetObjectRequest(bucket: $this->bucket(), key: $this->resolveKey($path)),
            $file
        );
    }

    public function symlink(string $link, string $target): void
    {
        $this->client()->putSymlink(
            new Oss\Models\PutSymlinkRequest(
                bucket: $this->bucket(),
                key: $this->resolveKey($link),
                target: $this->resolveKey($target),
            )
        );
    }

    /**
     * 获取上传 endpoint（不含路径），如 https://bucket.oss-cn-shanghai.aliyuncs.com
     */
    public function uploadEndpoint(): string
    {
        return sprintf('https://%s', $this->ossDomain());
    }

    /**
     * 获取图片元信息（ImageWidth、ImageHeight、Format、FileSize 等）。
     *
     * 自动识别参数：传入 path 或完整 URL 均可。
     * 文件不存在或非图片返回 null。
     *
     * @param string $pathOrUrl 相对路径或完整 URL
     * @return array|null
     */
    public function fetchImageInfo(string $pathOrUrl): ?array
    {
        $key = $this->resolvePathOrUrl($pathOrUrl);
        if ($key === null) {
            return null;
        }

        try {
            $result = $this->client()->getObject(
                new Oss\Models\GetObjectRequest(
                    bucket: $this->bucket(),
                    key: $key,
                    process: 'image/info',
                )
            );

            $data = json_decode($result->body->getContents(), true);

            return is_array($data) ? $data : null;
        } catch (Oss\Exception\OperationException $e) {
            $prev = $e->getPrevious();
            if ($prev instanceof Oss\Exception\ServiceException && $prev->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * 获取文件属性（大小、MIME、最后修改时间）。
     *
     * 自动识别参数：传入 path 或完整 URL 均可。
     * 与 Flysystem 的 fileAttributes() 不同：此方法接受 URL，且文件不存在返回 null 而非抛异常。
     *
     * @param string $pathOrUrl 相对路径或完整 URL
     * @return FileAttributes|null
     */
    public function fetchAttributes(string $pathOrUrl): ?FileAttributes
    {
        $key = $this->resolvePathOrUrl($pathOrUrl);
        if ($key === null) {
            return null;
        }

        try {
            $result = $this->client()->headObject(
                new Oss\Models\HeadObjectRequest(bucket: $this->bucket(), key: $key)
            );

            return new FileAttributes(
                $key,
                $result->contentLength ?? null,
                null,
                $result->lastModified?->getTimestamp(),
                $result->contentType ?? null
            );
        } catch (Oss\Exception\OperationException $e) {
            $prev = $e->getPrevious();
            if ($prev instanceof Oss\Exception\ServiceException && $prev->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    // ==================== 工具 ====================

    public static function watermarkText(string $text): string
    {
        return rtrim(strtr(base64_encode($text), ['+' => '-', '/' => '_']));
    }

    // ==================== 内部方法 ====================

    private function resolveKey(string $path): string
    {
        return ltrim($this->prefixer()->prefixPath($path), '/');
    }

    /**
     * 自动识别参数是 path 还是 URL，返回 OSS key。
     * URL 直接取 path 部分（不走 prefix），普通 path 走 resolveKey。
     */
    private function resolvePathOrUrl(string $pathOrUrl): ?string
    {
        if (HUrl::isUrlString($pathOrUrl)) {
            $parsed = HUrl::parse($pathOrUrl);
            if (!$parsed instanceof HUrl) {
                return null;
            }

            $key = ltrim($parsed->getPath(), '/');

            return !empty($key) ? $key : null;
        }

        return $this->resolveKey($pathOrUrl);
    }

    private function defaultAcl(): string
    {
        if (!empty($this->config['acl'])) {
            return $this->config['acl'];
        }

        $result = $this->client()->getBucketAcl(
            new Oss\Models\GetBucketAclRequest(bucket: $this->bucket())
        );

        return $result->accessControlList->grant ?? Acl::OSS_ACL_TYPE_PRIVATE;
    }

    private function matchUrlHost(string $url, ?string $domain): bool
    {
        if ($domain === null) {
            return false;
        }

        $urlObj = HUrl::parse($url);
        return $urlObj instanceof HUrl && $urlObj->getHost() === $domain;
    }

}
