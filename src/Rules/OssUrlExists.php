<?php

namespace HughCube\Laravel\AliOSS\Rules;

use Closure;
use HughCube\Laravel\AliOSS\OssAdapter;
use HughCube\PUrl\HUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;
use OSS\Core\OssException;

class OssUrlExists implements ValidationRule
{
    public const DOMAIN_CDN = 'cdn';
    public const DOMAIN_UPLOAD = 'upload';
    public const DOMAIN_OSS = 'oss';
    public const DOMAIN_OSS_INTERNAL = 'oss_internal';

    protected ?string $disk;

    protected bool $checkFileExists = true;

    /**
     * 允许的域名类型，null 表示不限制
     * @var array<string>|null
     */
    protected ?array $allowedDomainTypes = null;

    /**
     * 文件最小大小（字节）
     */
    protected ?int $minSize = null;

    /**
     * 文件最大大小（字节）
     */
    protected ?int $maxSize = null;

    /**
     * 允许的 MIME types
     * @var array<string>|null
     */
    protected ?array $allowedMimeTypes = null;

    protected ?string $failedReason = null;

    /**
     * 验证后获取的文件属性
     */
    protected ?FileAttributes $fileAttributes = null;

    /**
     * 检测到的域名类型
     */
    protected ?string $detectedDomainType = null;

    /**
     * @param string|null $disk OSS disk 名称，null 时使用默认的 'oss'
     */
    public function __construct(?string $disk = null)
    {
        $this->disk = $disk;
    }

    /**
     * 创建实例
     */
    public static function make(?string $disk = null): static
    {
        return new static($disk);
    }

    /**
     * 设置是否检查文件存在
     */
    public function checkExists(bool $check = true): static
    {
        $this->checkFileExists = $check;
        return $this;
    }

    /**
     * 只检查域名，不检查文件存在
     */
    public function domainOnly(): static
    {
        $this->checkFileExists = false;
        return $this;
    }

    /**
     * 限制只允许 CDN 域名
     */
    public function cdnDomain(): static
    {
        $this->allowedDomainTypes = [self::DOMAIN_CDN];
        return $this;
    }

    /**
     * 限制只允许 Upload 域名
     */
    public function uploadDomain(): static
    {
        $this->allowedDomainTypes = [self::DOMAIN_UPLOAD];
        return $this;
    }

    /**
     * 限制只允许 OSS 原始域名
     */
    public function ossDomain(bool $includeInternal = false): static
    {
        $this->allowedDomainTypes = $includeInternal
            ? [self::DOMAIN_OSS, self::DOMAIN_OSS_INTERNAL]
            : [self::DOMAIN_OSS];
        return $this;
    }

    /**
     * 设置允许的域名类型
     *
     * @param array<string> $types 允许的类型：DOMAIN_CDN, DOMAIN_UPLOAD, DOMAIN_OSS, DOMAIN_OSS_INTERNAL
     */
    public function allowedDomains(array $types): static
    {
        $this->allowedDomainTypes = $types;
        return $this;
    }

    /**
     * 允许任意已配置的域名（cdn、upload、oss）
     */
    public function anyDomain(): static
    {
        $this->allowedDomainTypes = [
            self::DOMAIN_CDN,
            self::DOMAIN_UPLOAD,
            self::DOMAIN_OSS,
            self::DOMAIN_OSS_INTERNAL,
        ];
        return $this;
    }

    /**
     * 设置文件最小大小
     *
     * @param int $bytes 最小字节数
     */
    public function minSize(int $bytes): static
    {
        $this->minSize = $bytes;
        return $this;
    }

    /**
     * 设置文件最大大小
     *
     * @param int $bytes 最大字节数
     */
    public function maxSize(int $bytes): static
    {
        $this->maxSize = $bytes;
        return $this;
    }

    /**
     * 设置文件大小范围
     *
     * @param int $min 最小字节数
     * @param int $max 最大字节数
     */
    public function sizeBetween(int $min, int $max): static
    {
        $this->minSize = $min;
        $this->maxSize = $max;
        return $this;
    }

    /**
     * 设置允许的 MIME types
     *
     * @param array<string> $mimeTypes 允许的 MIME types，支持通配符如 'image/*'
     */
    public function mimeTypes(array $mimeTypes): static
    {
        $this->allowedMimeTypes = $mimeTypes;
        return $this;
    }

    /**
     * 只允许图片类型
     */
    public function image(): static
    {
        $this->allowedMimeTypes = ['image/*'];
        return $this;
    }

    /**
     * 只允许视频类型
     */
    public function video(): static
    {
        $this->allowedMimeTypes = ['video/*'];
        return $this;
    }

    /**
     * 只允许音频类型
     */
    public function audio(): static
    {
        $this->allowedMimeTypes = ['audio/*'];
        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 重置状态
        $this->failedReason = null;
        $this->fileAttributes = null;
        $this->detectedDomainType = null;

        // 验证是否是有效的 URL
        if (!HUrl::isUrlString($value)) {
            $this->failedReason = 'invalid_url';
            $fail($this->message('invalid_url'));
            return;
        }

        $adapter = $this->getAdapter();
        if (!$adapter instanceof OssAdapter) {
            $this->failedReason = 'invalid_disk';
            $fail($this->message('invalid_disk'));
            return;
        }

        // 检查域名类型
        $this->detectedDomainType = $this->detectDomainType($adapter, $value);

        if ($this->allowedDomainTypes !== null) {
            if ($this->detectedDomainType === null) {
                $this->failedReason = 'domain_mismatch';
                $fail($this->message('domain_mismatch'));
                return;
            }

            if (!in_array($this->detectedDomainType, $this->allowedDomainTypes, true)) {
                $this->failedReason = 'domain_type_not_allowed';
                $fail($this->message('domain_type_not_allowed'));
                return;
            }
        } else {
            // 如果没有设置 allowedDomainTypes，则默认检查是否属于 bucket
            if (!$adapter->isBucketUrl($value)) {
                $this->failedReason = 'domain_mismatch';
                $fail($this->message('domain_mismatch'));
                return;
            }
        }

        // 检查文件是否存在及获取文件属性
        if ($this->checkFileExists || $this->minSize !== null || $this->maxSize !== null || $this->allowedMimeTypes !== null) {
            try {
                $path = ltrim(HUrl::parse($value)?->getPath() ?? '', '/');
                if (empty($path)) {
                    $this->failedReason = 'file_not_found';
                    $fail($this->message('file_not_found'));
                    return;
                }

                $this->fileAttributes = $adapter->getFileAttributes($path, new \League\Flysystem\Config(['with_prefix' => false]));
            } catch (OssException $e) {
                // 404 表示文件不存在，是验证失败
                if ((int) $e->getHTTPStatus() === 404) {
                    $this->failedReason = 'file_not_found';
                    $fail($this->message('file_not_found'));
                    return;
                }
                // 其他 OSS 错误（网络、权限等）直接抛出异常
                throw $e;
            }
        }

        // 检查文件大小
        if ($this->fileAttributes !== null) {
            $fileSize = $this->fileAttributes->fileSize();

            if ($this->minSize !== null && $fileSize !== null && $fileSize < $this->minSize) {
                $this->failedReason = 'file_too_small';
                $fail($this->message('file_too_small'));
                return;
            }

            if ($this->maxSize !== null && $fileSize !== null && $fileSize > $this->maxSize) {
                $this->failedReason = 'file_too_large';
                $fail($this->message('file_too_large'));
                return;
            }

            // 检查 MIME type
            if ($this->allowedMimeTypes !== null) {
                $mimeType = $this->fileAttributes->mimeType();
                if ($mimeType === null || !$this->matchMimeType($mimeType, $this->allowedMimeTypes)) {
                    $this->failedReason = 'mime_type_not_allowed';
                    $fail($this->message('mime_type_not_allowed'));
                    return;
                }
            }
        }
    }

    /**
     * 检测 URL 的域名类型
     */
    protected function detectDomainType(OssAdapter $adapter, string $url): ?string
    {
        $urlObj = HUrl::parse($url);
        if (!$urlObj instanceof HUrl) {
            return null;
        }

        $host = $urlObj->getHost();

        // 检查 CDN 域名
        if ($adapter->getCdnDomain() !== null && $host === $adapter->getCdnDomain()) {
            return self::DOMAIN_CDN;
        }

        // 检查 Upload 域名
        if ($adapter->getUploadDomain() !== null && $host === $adapter->getUploadDomain()) {
            return self::DOMAIN_UPLOAD;
        }

        // 检查 OSS 原始域名
        if ($host === $adapter->getOssOriginalDomain(false)) {
            return self::DOMAIN_OSS;
        }

        // 检查 OSS 内网域名
        if ($host === $adapter->getOssOriginalDomain(true)) {
            return self::DOMAIN_OSS_INTERNAL;
        }

        return null;
    }

    /**
     * 检查 MIME type 是否匹配
     *
     * @param string $mimeType 文件的 MIME type
     * @param array<string> $allowedTypes 允许的类型，支持通配符
     */
    protected function matchMimeType(string $mimeType, array $allowedTypes): bool
    {
        foreach ($allowedTypes as $allowed) {
            // 完全匹配
            if ($mimeType === $allowed) {
                return true;
            }

            // 通配符匹配，如 'image/*'
            if (str_ends_with($allowed, '/*')) {
                $prefix = substr($allowed, 0, -1); // 'image/'
                if (str_starts_with($mimeType, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getAdapter(): ?OssAdapter
    {
        $disk = Storage::disk($this->disk ?: 'oss');
        if (!$disk instanceof FilesystemAdapter) {
            return null;
        }

        $adapter = $disk->getAdapter();
        return $adapter instanceof OssAdapter ? $adapter : null;
    }

    protected function message(string $key): string
    {
        $messages = [
            'invalid_url' => 'The :attribute must be a valid URL string.',
            'invalid_disk' => 'The :attribute validation failed: invalid OSS disk configuration.',
            'domain_mismatch' => 'The :attribute does not belong to the configured OSS bucket.',
            'domain_type_not_allowed' => 'The :attribute domain type is not allowed.',
            'file_not_found' => 'The :attribute does not exist in OSS.',
            'file_too_small' => 'The :attribute file is too small.',
            'file_too_large' => 'The :attribute file is too large.',
            'mime_type_not_allowed' => 'The :attribute file type is not allowed.',
        ];

        return $messages[$key] ?? 'The :attribute is invalid.';
    }

    /**
     * 获取失败原因
     */
    public function getFailedReason(): ?string
    {
        return $this->failedReason;
    }

    /**
     * 获取验证后的文件属性
     */
    public function getFileAttributes(): ?FileAttributes
    {
        return $this->fileAttributes;
    }

    /**
     * 获取文件大小（字节）
     */
    public function getFileSize(): ?int
    {
        return $this->fileAttributes?->fileSize();
    }

    /**
     * 获取文件 MIME type
     */
    public function getMimeType(): ?string
    {
        return $this->fileAttributes?->mimeType();
    }

    /**
     * 获取检测到的域名类型
     */
    public function getDetectedDomainType(): ?string
    {
        return $this->detectedDomainType;
    }

    /**
     * 是否是 CDN 域名
     */
    public function isCdnDomain(): bool
    {
        return $this->detectedDomainType === self::DOMAIN_CDN;
    }

    /**
     * 是否是 Upload 域名
     */
    public function isUploadDomain(): bool
    {
        return $this->detectedDomainType === self::DOMAIN_UPLOAD;
    }

    /**
     * 是否是 OSS 原始域名
     */
    public function isOssDomain(): bool
    {
        return $this->detectedDomainType === self::DOMAIN_OSS;
    }

    /**
     * 是否是 OSS 内网域名
     */
    public function isOssInternalDomain(): bool
    {
        return $this->detectedDomainType === self::DOMAIN_OSS_INTERNAL;
    }
}
