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

    /**
     * 允许的文件扩展名
     * @var array<string>|null
     */
    protected ?array $allowedExtensions = null;

    /**
     * 允许的目录前缀
     * @var array<string>|null
     */
    protected ?array $allowedDirectories = null;

    /**
     * 文件路径正则匹配模式
     */
    protected ?string $pathPattern = null;

    /**
     * 文件名正则匹配模式
     */
    protected ?string $filenamePattern = null;

    /**
     * 禁止的文件扩展名
     * @var array<string>|null
     */
    protected ?array $forbiddenExtensions = null;

    /**
     * 禁止的目录前缀
     * @var array<string>|null
     */
    protected ?array $forbiddenDirectories = null;

    /**
     * 文件名最大长度
     */
    protected ?int $filenameMaxLength = null;

    /**
     * 解析后的文件路径
     */
    protected ?string $parsedPath = null;

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

    /**
     * 只允许文档类型（PDF、Word、Excel、PPT 等）
     */
    public function document(): static
    {
        $this->allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'application/rtf',
        ];
        return $this;
    }

    /**
     * 只允许 PDF 类型
     */
    public function pdf(): static
    {
        $this->allowedMimeTypes = ['application/pdf'];
        return $this;
    }

    /**
     * 只允许 Excel 类型
     */
    public function excel(): static
    {
        $this->allowedMimeTypes = [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        return $this;
    }

    /**
     * 只允许 Word 类型
     */
    public function word(): static
    {
        $this->allowedMimeTypes = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        return $this;
    }

    /**
     * 只允许 PPT 类型
     */
    public function ppt(): static
    {
        $this->allowedMimeTypes = [
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
        return $this;
    }

    /**
     * 只允许压缩包类型
     */
    public function archive(): static
    {
        $this->allowedMimeTypes = [
            'application/zip',
            'application/x-rar-compressed',
            'application/vnd.rar',
            'application/x-7z-compressed',
            'application/gzip',
            'application/x-tar',
            'application/x-bzip2',
        ];
        return $this;
    }

    /**
     * 只允许文本类型
     */
    public function text(): static
    {
        $this->allowedMimeTypes = ['text/*'];
        return $this;
    }

    /**
     * 只允许 JSON 类型
     */
    public function json(): static
    {
        $this->allowedMimeTypes = ['application/json'];
        return $this;
    }

    /**
     * 只允许 XML 类型
     */
    public function xml(): static
    {
        $this->allowedMimeTypes = [
            'application/xml',
            'text/xml',
        ];
        return $this;
    }

    /**
     * 只允许媒体类型（图片、视频、音频）
     */
    public function media(): static
    {
        $this->allowedMimeTypes = ['image/*', 'video/*', 'audio/*'];
        return $this;
    }

    /**
     * 设置允许的文件扩展名
     *
     * @param array<string> $extensions 允许的扩展名，不含点号，如 ['jpg', 'png']
     */
    public function extensions(array $extensions): static
    {
        $this->allowedExtensions = array_map('strtolower', $extensions);
        return $this;
    }

    /**
     * 设置文件必须在指定目录下
     *
     * @param string $directory 目录路径，如 'uploads/images'
     */
    public function directory(string $directory): static
    {
        $this->allowedDirectories = [trim($directory, '/')];
        return $this;
    }

    /**
     * 设置文件必须在指定目录之一下
     *
     * @param array<string> $directories 目录路径数组
     */
    public function directories(array $directories): static
    {
        $this->allowedDirectories = array_map(fn($dir) => trim($dir, '/'), $directories);
        return $this;
    }

    /**
     * 设置文件路径必须匹配正则表达式
     *
     * @param string $pattern 正则表达式，如 '/^uploads\/\d{4}\//'
     */
    public function pathMatches(string $pattern): static
    {
        $this->pathPattern = $pattern;
        return $this;
    }

    /**
     * 设置文件名必须匹配正则表达式
     *
     * @param string $pattern 正则表达式，如 '/^\d+_.*\.jpg$/'
     */
    public function filenameMatches(string $pattern): static
    {
        $this->filenamePattern = $pattern;
        return $this;
    }

    /**
     * 设置禁止的文件扩展名
     *
     * @param array<string> $extensions 禁止的扩展名，不含点号，如 ['exe', 'php']
     */
    public function exceptExtensions(array $extensions): static
    {
        $this->forbiddenExtensions = array_map('strtolower', $extensions);
        return $this;
    }

    /**
     * 设置禁止的目录
     *
     * @param string $directory 禁止的目录路径
     */
    public function exceptDirectory(string $directory): static
    {
        $this->forbiddenDirectories = [trim($directory, '/')];
        return $this;
    }

    /**
     * 设置禁止的目录列表
     *
     * @param array<string> $directories 禁止的目录路径数组
     */
    public function exceptDirectories(array $directories): static
    {
        $this->forbiddenDirectories = array_map(fn($dir) => trim($dir, '/'), $directories);
        return $this;
    }

    /**
     * 设置文件名最大长度
     *
     * @param int $length 最大长度（字符数）
     */
    public function filenameMaxLength(int $length): static
    {
        $this->filenameMaxLength = $length;
        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 重置状态
        $this->failedReason = null;
        $this->fileAttributes = null;
        $this->detectedDomainType = null;
        $this->parsedPath = null;

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

        // 解析并保存路径
        $this->parsedPath = ltrim(HUrl::parse($value)?->getPath() ?? '', '/');
        if (empty($this->parsedPath)) {
            $this->failedReason = 'invalid_path';
            $fail($this->message('invalid_path'));
            return;
        }

        // 检查文件扩展名（白名单）
        $extension = strtolower(pathinfo($this->parsedPath, PATHINFO_EXTENSION));
        if ($this->allowedExtensions !== null) {
            if (!in_array($extension, $this->allowedExtensions, true)) {
                $this->failedReason = 'extension_not_allowed';
                $fail($this->message('extension_not_allowed'));
                return;
            }
        }

        // 检查文件扩展名（黑名单）
        if ($this->forbiddenExtensions !== null) {
            if (in_array($extension, $this->forbiddenExtensions, true)) {
                $this->failedReason = 'extension_forbidden';
                $fail($this->message('extension_forbidden'));
                return;
            }
        }

        // 检查目录（白名单）
        if ($this->allowedDirectories !== null) {
            $inAllowedDir = false;
            foreach ($this->allowedDirectories as $dir) {
                // 兼容空目录（根目录）
                if ($dir === '') {
                    $inAllowedDir = true;
                    break;
                }
                if (str_starts_with($this->parsedPath, $dir . '/') || $this->parsedPath === $dir) {
                    $inAllowedDir = true;
                    break;
                }
            }
            if (!$inAllowedDir) {
                $this->failedReason = 'directory_not_allowed';
                $fail($this->message('directory_not_allowed'));
                return;
            }
        }

        // 检查目录（黑名单）
        if ($this->forbiddenDirectories !== null) {
            foreach ($this->forbiddenDirectories as $dir) {
                if ($dir === '') {
                    continue;
                }
                if (str_starts_with($this->parsedPath, $dir . '/') || $this->parsedPath === $dir) {
                    $this->failedReason = 'directory_forbidden';
                    $fail($this->message('directory_forbidden'));
                    return;
                }
            }
        }

        // 检查路径正则
        if ($this->pathPattern !== null) {
            if (!preg_match($this->pathPattern, $this->parsedPath)) {
                $this->failedReason = 'path_pattern_mismatch';
                $fail($this->message('path_pattern_mismatch'));
                return;
            }
        }

        // 检查文件名正则
        $filename = pathinfo($this->parsedPath, PATHINFO_BASENAME);
        if ($this->filenamePattern !== null) {
            if (!preg_match($this->filenamePattern, $filename)) {
                $this->failedReason = 'filename_pattern_mismatch';
                $fail($this->message('filename_pattern_mismatch'));
                return;
            }
        }

        // 检查文件名长度
        if ($this->filenameMaxLength !== null) {
            if (mb_strlen($filename) > $this->filenameMaxLength) {
                $this->failedReason = 'filename_too_long';
                $fail($this->message('filename_too_long'));
                return;
            }
        }

        // 检查文件是否存在及获取文件属性
        if ($this->checkFileExists || $this->minSize !== null || $this->maxSize !== null || $this->allowedMimeTypes !== null) {
            try {
                $this->fileAttributes = $adapter->getFileAttributes($this->parsedPath, new \League\Flysystem\Config(['with_prefix' => false]));
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
            'invalid_path' => 'The :attribute has an invalid path.',
            'domain_mismatch' => 'The :attribute does not belong to the configured OSS bucket.',
            'domain_type_not_allowed' => 'The :attribute domain type is not allowed.',
            'file_not_found' => 'The :attribute does not exist in OSS.',
            'file_too_small' => 'The :attribute file is too small.',
            'file_too_large' => 'The :attribute file is too large.',
            'mime_type_not_allowed' => 'The :attribute file type is not allowed.',
            'extension_not_allowed' => 'The :attribute file extension is not allowed.',
            'extension_forbidden' => 'The :attribute file extension is forbidden.',
            'directory_not_allowed' => 'The :attribute is not in an allowed directory.',
            'directory_forbidden' => 'The :attribute is in a forbidden directory.',
            'path_pattern_mismatch' => 'The :attribute path does not match the required pattern.',
            'filename_pattern_mismatch' => 'The :attribute filename does not match the required pattern.',
            'filename_too_long' => 'The :attribute filename is too long.',
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

    /**
     * 获取解析后的文件路径
     */
    public function getPath(): ?string
    {
        return $this->parsedPath;
    }

    /**
     * 获取文件名（含扩展名）
     */
    public function getFilename(): ?string
    {
        return $this->parsedPath !== null ? pathinfo($this->parsedPath, PATHINFO_BASENAME) : null;
    }

    /**
     * 获取文件扩展名（不含点号）
     */
    public function getExtension(): ?string
    {
        return $this->parsedPath !== null ? pathinfo($this->parsedPath, PATHINFO_EXTENSION) : null;
    }

    /**
     * 获取文件所在目录
     */
    public function getDirectory(): ?string
    {
        return $this->parsedPath !== null ? pathinfo($this->parsedPath, PATHINFO_DIRNAME) : null;
    }
}
