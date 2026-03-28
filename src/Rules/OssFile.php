<?php

namespace HughCube\Laravel\AliOSS\Rules;

use Closure;
use HughCube\Laravel\AliOSS\OssAdapter;
use HughCube\PUrl\HUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;

class OssFile implements ValidationRule
{
    protected ?string $disk;
    protected bool $checkFileExists = true;
    protected ?array $allowedDomainTypes = null;
    protected ?int $minSize = null;
    protected ?int $maxSize = null;
    protected ?array $allowedMimeTypes = null;
    protected ?array $allowedExtensions = null;
    protected ?array $forbiddenExtensions = null;
    protected ?array $allowedDirectories = null;
    protected ?array $forbiddenDirectories = null;
    protected ?int $filenameMaxLen = null;
    protected ?int $maxWidth = null;
    protected ?int $maxHeight = null;
    protected ?array $exactAspectRatio = null;
    protected ?array $minAspectRatio = null;
    protected ?array $maxAspectRatio = null;

    protected ?string $parsedPath = null;
    protected ?string $failReason = null;
    protected ?FileAttributes $fileAttrs = null;
    protected ?string $detectedDomain = null;
    protected ?array $imageInfo = null;

    public function __construct(?string $disk = null)
    {
        $this->disk = $disk;
    }

    public static function make(?string $disk = null): static
    {
        return new static($disk);
    }

    // ==================== 域名约束 ====================

    public function cdnDomain(): static
    {
        $this->allowedDomainTypes = [OssAdapter::DOMAIN_CDN];
        return $this;
    }

    public function uploadDomain(): static
    {
        $this->allowedDomainTypes = [OssAdapter::DOMAIN_UPLOAD];
        return $this;
    }

    public function ossDomain(): static
    {
        $this->allowedDomainTypes = [OssAdapter::DOMAIN_OSS, OssAdapter::DOMAIN_OSS_INTERNAL];
        return $this;
    }

    public function anyDomain(): static
    {
        $this->allowedDomainTypes = [
            OssAdapter::DOMAIN_CDN,
            OssAdapter::DOMAIN_UPLOAD,
            OssAdapter::DOMAIN_OSS,
            OssAdapter::DOMAIN_OSS_INTERNAL,
        ];
        return $this;
    }

    // ==================== MIME 类型 ====================

    public function mimeTypes(array $types): static
    {
        $this->allowedMimeTypes = $types;
        return $this;
    }

    public function image(): static
    {
        return $this->mimeTypes(['image/*']);
    }
    public function video(): static
    {
        return $this->mimeTypes(['video/*']);
    }
    public function audio(): static
    {
        return $this->mimeTypes(['audio/*']);
    }
    public function media(): static
    {
        return $this->mimeTypes(['image/*', 'video/*', 'audio/*']);
    }

    public function document(): static
    {
        return $this->mimeTypes([
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'application/rtf',
        ]);
    }

    public function pdf(): static
    {
        return $this->mimeTypes(['application/pdf']);
    }

    public function word(): static
    {
        return $this->mimeTypes([
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function excel(): static
    {
        return $this->mimeTypes([
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function ppt(): static
    {
        return $this->mimeTypes([
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ]);
    }

    public function archive(): static
    {
        return $this->mimeTypes([
            'application/zip',
            'application/x-rar-compressed',
            'application/vnd.rar',
            'application/x-7z-compressed',
            'application/gzip',
            'application/x-tar',
            'application/x-bzip2',
        ]);
    }

    public function text(): static
    {
        return $this->mimeTypes(['text/*']);
    }
    public function json(): static
    {
        return $this->mimeTypes(['application/json']);
    }

    public function xml(): static
    {
        return $this->mimeTypes(['application/xml', 'text/xml']);
    }

    // ==================== 扩展名 ====================

    public function extensions(array $extensions): static
    {
        $this->allowedExtensions = array_map('strtolower', $extensions);
        return $this;
    }

    public function exceptExtensions(array $extensions): static
    {
        $this->forbiddenExtensions = array_map('strtolower', $extensions);
        return $this;
    }

    // ==================== 大小 ====================

    public function minSize(int $bytes): static
    {
        $this->minSize = $bytes;
        return $this;
    }

    public function maxSize(int $bytes): static
    {
        $this->maxSize = $bytes;
        return $this;
    }

    public function sizeBetween(int $min, int $max): static
    {
        $this->minSize = $min;
        $this->maxSize = $max;
        return $this;
    }

    // ==================== 图片尺寸 ====================

    public function maxWidth(int $px): static
    {
        $this->maxWidth = $px;
        return $this;
    }

    public function maxHeight(int $px): static
    {
        $this->maxHeight = $px;
        return $this;
    }

    public function aspectRatio(int $w, int $h): static
    {
        $this->exactAspectRatio = [$w, $h];
        return $this;
    }

    public function minAspectRatio(int $w, int $h): static
    {
        $this->minAspectRatio = [$w, $h];
        return $this;
    }

    public function maxAspectRatio(int $w, int $h): static
    {
        $this->maxAspectRatio = [$w, $h];
        return $this;
    }

    // ==================== 路径 ====================

    public function directory(string $dir): static
    {
        $this->allowedDirectories = [trim($dir, '/')];
        return $this;
    }

    public function directories(array $dirs): static
    {
        $this->allowedDirectories = array_map(fn($d) => trim($d, '/'), $dirs);
        return $this;
    }

    public function exceptDirectory(string $dir): static
    {
        $this->forbiddenDirectories = $this->forbiddenDirectories ?? [];
        $this->forbiddenDirectories[] = trim($dir, '/');
        return $this;
    }

    // ==================== 文件名 ====================

    public function filenameMaxLength(int $length): static
    {
        $this->filenameMaxLen = $length;
        return $this;
    }

    // ==================== 行为 ====================

    public function domainOnly(): static
    {
        $this->checkFileExists = false;
        return $this;
    }

    public function checkExists(bool $check = true): static
    {
        $this->checkFileExists = $check;
        return $this;
    }

    // ==================== 验证 ====================

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->resetState();

        if (!HUrl::isUrlString($value)) {
            $this->failWith('invalid_url', $fail);
            return;
        }

        $adapter = $this->resolveAdapter();
        if ($adapter === null) {
            $this->failWith('invalid_disk', $fail);
            return;
        }

        if ($reason = $this->validateDomain($adapter, $value)) {
            $this->failWith($reason, $fail);
            return;
        }

        if ($reason = $this->validatePath($value)) {
            $this->failWith($reason, $fail);
            return;
        }

        if ($reason = $this->validateFileAttributes($adapter)) {
            $this->failWith($reason, $fail);
            return;
        }

        if ($reason = $this->validateImageDimensions($adapter)) {
            $this->failWith($reason, $fail);
        }
    }

    // ==================== 查询方法 ====================

    public function fileAttributes(): ?FileAttributes
    {
        return $this->fileAttrs;
    }
    public function fileSize(): ?int
    {
        return $this->fileAttrs?->fileSize();
    }
    public function mimeType(): ?string
    {
        return $this->fileAttrs?->mimeType();
    }
    public function path(): ?string
    {
        return $this->parsedPath;
    }

    public function filename(): ?string
    {
        return $this->parsedPath !== null ? pathinfo($this->parsedPath, PATHINFO_BASENAME) : null;
    }

    public function extension(): ?string
    {
        return $this->parsedPath !== null ? pathinfo($this->parsedPath, PATHINFO_EXTENSION) : null;
    }

    public function getDirectory(): ?string
    {
        return $this->parsedPath !== null ? pathinfo($this->parsedPath, PATHINFO_DIRNAME) : null;
    }

    public function domainType(): ?string
    {
        return $this->detectedDomain;
    }
    public function failedReason(): ?string
    {
        return $this->failReason;
    }
    public function isCdnDomain(): bool
    {
        return $this->detectedDomain === OssAdapter::DOMAIN_CDN;
    }
    public function isUploadDomain(): bool
    {
        return $this->detectedDomain === OssAdapter::DOMAIN_UPLOAD;
    }
    public function isOssDomain(): bool
    {
        return $this->detectedDomain === OssAdapter::DOMAIN_OSS;
    }

    // ==================== 内部方法 ====================

    protected function resetState(): void
    {
        $this->failReason = null;
        $this->fileAttrs = null;
        $this->detectedDomain = null;
        $this->parsedPath = null;
        $this->imageInfo = null;
    }

    protected function failWith(string $reason, Closure $fail): void
    {
        $this->failReason = $reason;
        $fail($this->message($reason));
    }

    protected function validateDomain(OssAdapter $adapter, string $value): ?string
    {
        $this->detectedDomain = $this->detectDomain($adapter, $value);

        if ($this->allowedDomainTypes !== null) {
            if ($this->detectedDomain === null || !in_array($this->detectedDomain, $this->allowedDomainTypes, true)) {
                return $this->detectedDomain === null ? 'domain_mismatch' : 'domain_type_not_allowed';
            }
        } elseif (!$adapter->isBucketUrl($value)) {
            return 'domain_mismatch';
        }

        return null;
    }

    protected function validatePath(string $value): ?string
    {
        $this->parsedPath = ltrim(HUrl::parse($value)?->getPath() ?? '', '/');
        if (empty($this->parsedPath)) {
            return 'invalid_path';
        }

        $ext = strtolower(pathinfo($this->parsedPath, PATHINFO_EXTENSION));

        if ($this->allowedExtensions !== null && !in_array($ext, $this->allowedExtensions, true)) {
            return 'extension_not_allowed';
        }

        if ($this->forbiddenExtensions !== null && in_array($ext, $this->forbiddenExtensions, true)) {
            return 'extension_forbidden';
        }

        if ($this->allowedDirectories !== null) {
            $inAllowed = false;
            foreach ($this->allowedDirectories as $dir) {
                if ($dir === '' || str_starts_with($this->parsedPath, $dir . '/') || $this->parsedPath === $dir) {
                    $inAllowed = true;
                    break;
                }
            }
            if (!$inAllowed) {
                return 'directory_not_allowed';
            }
        }

        if ($this->forbiddenDirectories !== null) {
            foreach ($this->forbiddenDirectories as $dir) {
                if ($dir !== '' && (str_starts_with($this->parsedPath, $dir . '/') || $this->parsedPath === $dir)) {
                    return 'directory_forbidden';
                }
            }
        }

        $filename = pathinfo($this->parsedPath, PATHINFO_BASENAME);
        if ($this->filenameMaxLen !== null && mb_strlen($filename) > $this->filenameMaxLen) {
            return 'filename_too_long';
        }

        return null;
    }

    protected function validateFileAttributes(OssAdapter $adapter): ?string
    {
        $needFileAttrs = $this->checkFileExists
            || $this->minSize !== null
            || $this->maxSize !== null
            || $this->allowedMimeTypes !== null;

        if (!$needFileAttrs) {
            return null;
        }

        $this->fileAttrs = $adapter->fetchAttributes('/' . $this->parsedPath);
        if ($this->fileAttrs === null) {
            return 'file_not_found';
        }

        $size = $this->fileAttrs->fileSize();

        if ($this->minSize !== null && $size !== null && $size < $this->minSize) {
            return 'file_too_small';
        }

        if ($this->maxSize !== null && $size !== null && $size > $this->maxSize) {
            return 'file_too_large';
        }

        if ($this->allowedMimeTypes !== null) {
            $mime = $this->fileAttrs->mimeType();
            if ($mime === null || !$this->matchMimeType($mime, $this->allowedMimeTypes)) {
                return 'mime_type_not_allowed';
            }
        }

        return null;
    }

    protected function validateImageDimensions(OssAdapter $adapter): ?string
    {
        $needImageInfo = $this->maxWidth !== null
            || $this->maxHeight !== null
            || $this->exactAspectRatio !== null
            || $this->minAspectRatio !== null
            || $this->maxAspectRatio !== null;

        if (!$needImageInfo) {
            return null;
        }

        $this->imageInfo = $adapter->fetchImageInfo('/' . $this->parsedPath);
        if ($this->imageInfo === null) {
            return 'file_not_found';
        }

        $width = (int) ($this->imageInfo['ImageWidth']['value'] ?? 0);
        $height = (int) ($this->imageInfo['ImageHeight']['value'] ?? 0);

        if ($this->maxWidth !== null && $width > $this->maxWidth) {
            return 'image_too_wide';
        }

        if ($this->maxHeight !== null && $height > $this->maxHeight) {
            return 'image_too_tall';
        }

        if ($height > 0 && $width > 0) {
            $ratio = $width / $height;

            if ($this->exactAspectRatio !== null) {
                $expected = $this->exactAspectRatio[0] / $this->exactAspectRatio[1];
                if (abs($ratio - $expected) > 0.01) {
                    return 'aspect_ratio_mismatch';
                }
            }

            if ($this->minAspectRatio !== null) {
                $min = $this->minAspectRatio[0] / $this->minAspectRatio[1];
                if ($ratio < $min - 0.01) {
                    return 'aspect_ratio_too_narrow';
                }
            }

            if ($this->maxAspectRatio !== null) {
                $max = $this->maxAspectRatio[0] / $this->maxAspectRatio[1];
                if ($ratio > $max + 0.01) {
                    return 'aspect_ratio_too_wide';
                }
            }
        }

        return null;
    }

    protected function resolveAdapter(): ?OssAdapter
    {
        $disk = Storage::disk($this->disk ?: 'oss');
        if (!$disk instanceof FilesystemAdapter) {
            return null;
        }

        $adapter = $disk->getAdapter();
        return $adapter instanceof OssAdapter ? $adapter : null;
    }

    protected function detectDomain(OssAdapter $adapter, string $url): ?string
    {
        if ($adapter->isCdnUrl($url)) {
            return OssAdapter::DOMAIN_CDN;
        }
        if ($adapter->isUploadUrl($url)) {
            return OssAdapter::DOMAIN_UPLOAD;
        }
        if ($adapter->isOssUrl($url)) {
            return OssAdapter::DOMAIN_OSS;
        }
        if ($adapter->isOssInternalUrl($url)) {
            return OssAdapter::DOMAIN_OSS_INTERNAL;
        }
        return null;
    }

    protected function matchMimeType(string $mimeType, array $allowed): bool
    {
        foreach ($allowed as $pattern) {
            if ($mimeType === $pattern) {
                return true;
            }
            if (str_ends_with($pattern, '/*') && str_starts_with($mimeType, substr($pattern, 0, -1))) {
                return true;
            }
        }
        return false;
    }

    protected function message(string $key): string
    {
        $messages = [
            'invalid_url'             => 'The :attribute must be a valid URL string.',
            'invalid_disk'            => 'The :attribute validation failed: invalid OSS disk configuration.',
            'invalid_path'            => 'The :attribute has an invalid path.',
            'domain_mismatch'         => 'The :attribute does not belong to the configured OSS bucket.',
            'domain_type_not_allowed' => 'The :attribute domain type is not allowed.',
            'file_not_found'          => 'The :attribute does not exist in OSS.',
            'file_too_small'          => 'The :attribute file is too small.',
            'file_too_large'          => 'The :attribute file is too large.',
            'mime_type_not_allowed'   => 'The :attribute file type is not allowed.',
            'extension_not_allowed'   => 'The :attribute file extension is not allowed.',
            'extension_forbidden'     => 'The :attribute file extension is forbidden.',
            'directory_not_allowed'   => 'The :attribute is not in an allowed directory.',
            'directory_forbidden'     => 'The :attribute is in a forbidden directory.',
            'filename_too_long'       => 'The :attribute filename is too long.',
            'image_too_wide'          => 'The :attribute image is too wide.',
            'image_too_tall'          => 'The :attribute image is too tall.',
            'aspect_ratio_mismatch'   => 'The :attribute image aspect ratio does not match.',
            'aspect_ratio_too_narrow' => 'The :attribute image aspect ratio is too narrow.',
            'aspect_ratio_too_wide'   => 'The :attribute image aspect ratio is too wide.',
        ];

        return $messages[$key] ?? 'The :attribute is invalid.';
    }
}
