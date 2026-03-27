<?php

namespace HughCube\Laravel\AliOSS\Rules;

use AlibabaCloud\Oss\V2 as Oss;
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

    public function image(): static { return $this->mimeTypes(['image/*']); }
    public function video(): static { return $this->mimeTypes(['video/*']); }
    public function audio(): static { return $this->mimeTypes(['audio/*']); }
    public function media(): static { return $this->mimeTypes(['image/*', 'video/*', 'audio/*']); }

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

    public function pdf(): static { return $this->mimeTypes(['application/pdf']); }

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

    public function text(): static { return $this->mimeTypes(['text/*']); }
    public function json(): static { return $this->mimeTypes(['application/json']); }

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
        $this->failReason = null;
        $this->fileAttrs = null;
        $this->detectedDomain = null;
        $this->parsedPath = null;
        $this->imageInfo = null;

        if (!HUrl::isUrlString($value)) {
            $this->failReason = 'invalid_url';
            $fail($this->message('invalid_url'));
            return;
        }

        $adapter = $this->resolveAdapter();
        if ($adapter === null) {
            $this->failReason = 'invalid_disk';
            $fail($this->message('invalid_disk'));
            return;
        }

        // 域名检查
        $this->detectedDomain = $this->detectDomain($adapter, $value);

        if ($this->allowedDomainTypes !== null) {
            if ($this->detectedDomain === null || !in_array($this->detectedDomain, $this->allowedDomainTypes, true)) {
                $this->failReason = $this->detectedDomain === null ? 'domain_mismatch' : 'domain_type_not_allowed';
                $fail($this->message($this->failReason));
                return;
            }
        } elseif (!$adapter->isBucketUrl($value)) {
            $this->failReason = 'domain_mismatch';
            $fail($this->message('domain_mismatch'));
            return;
        }

        // 解析路径
        $this->parsedPath = ltrim(HUrl::parse($value)?->getPath() ?? '', '/');
        if (empty($this->parsedPath)) {
            $this->failReason = 'invalid_path';
            $fail($this->message('invalid_path'));
            return;
        }

        // 扩展名白名单
        $ext = strtolower(pathinfo($this->parsedPath, PATHINFO_EXTENSION));
        if ($this->allowedExtensions !== null && !in_array($ext, $this->allowedExtensions, true)) {
            $this->failReason = 'extension_not_allowed';
            $fail($this->message('extension_not_allowed'));
            return;
        }

        // 扩展名黑名单
        if ($this->forbiddenExtensions !== null && in_array($ext, $this->forbiddenExtensions, true)) {
            $this->failReason = 'extension_forbidden';
            $fail($this->message('extension_forbidden'));
            return;
        }

        // 目录白名单
        if ($this->allowedDirectories !== null) {
            $inAllowed = false;
            foreach ($this->allowedDirectories as $dir) {
                if ($dir === '' || str_starts_with($this->parsedPath, $dir . '/') || $this->parsedPath === $dir) {
                    $inAllowed = true;
                    break;
                }
            }
            if (!$inAllowed) {
                $this->failReason = 'directory_not_allowed';
                $fail($this->message('directory_not_allowed'));
                return;
            }
        }

        // 目录黑名单
        if ($this->forbiddenDirectories !== null) {
            foreach ($this->forbiddenDirectories as $dir) {
                if ($dir !== '' && (str_starts_with($this->parsedPath, $dir . '/') || $this->parsedPath === $dir)) {
                    $this->failReason = 'directory_forbidden';
                    $fail($this->message('directory_forbidden'));
                    return;
                }
            }
        }

        // 文件名长度
        $filename = pathinfo($this->parsedPath, PATHINFO_BASENAME);
        if ($this->filenameMaxLen !== null && mb_strlen($filename) > $this->filenameMaxLen) {
            $this->failReason = 'filename_too_long';
            $fail($this->message('filename_too_long'));
            return;
        }

        // 需要获取文件属性的条件
        $needFileAttrs = $this->checkFileExists
            || $this->minSize !== null
            || $this->maxSize !== null
            || $this->allowedMimeTypes !== null;

        if ($needFileAttrs) {
            try {
                $result = $adapter->client()->headObject(
                    new Oss\Models\HeadObjectRequest(bucket: $adapter->bucket(), key: $this->parsedPath)
                );

                $this->fileAttrs = new FileAttributes(
                    $this->parsedPath,
                    $result->contentLength ?? null,
                    null,
                    $result->lastModified?->getTimestamp(),
                    $result->contentType ?? null
                );
            } catch (Oss\Exception\OperationException $e) {
                $prev = $e->getPrevious();
                if ($prev instanceof Oss\Exception\ServiceException && $prev->getStatusCode() === 404) {
                    $this->failReason = 'file_not_found';
                    $fail($this->message('file_not_found'));
                    return;
                }
                throw $e;
            }
        }

        // 大小验证
        if ($this->fileAttrs !== null) {
            $size = $this->fileAttrs->fileSize();

            if ($this->minSize !== null && $size !== null && $size < $this->minSize) {
                $this->failReason = 'file_too_small';
                $fail($this->message('file_too_small'));
                return;
            }

            if ($this->maxSize !== null && $size !== null && $size > $this->maxSize) {
                $this->failReason = 'file_too_large';
                $fail($this->message('file_too_large'));
                return;
            }

            // MIME 类型验证
            if ($this->allowedMimeTypes !== null) {
                $mime = $this->fileAttrs->mimeType();
                if ($mime === null || !$this->matchMimeType($mime, $this->allowedMimeTypes)) {
                    $this->failReason = 'mime_type_not_allowed';
                    $fail($this->message('mime_type_not_allowed'));
                    return;
                }
            }
        }

        // 图片尺寸验证
        $needImageInfo = $this->maxWidth !== null
            || $this->maxHeight !== null
            || $this->exactAspectRatio !== null
            || $this->minAspectRatio !== null
            || $this->maxAspectRatio !== null;

        if ($needImageInfo) {
            $this->imageInfo = $this->fetchImageInfo($adapter, $this->parsedPath);

            if ($this->imageInfo === null) {
                $this->failReason = 'file_not_found';
                $fail($this->message('file_not_found'));
                return;
            }

            $width = (int) ($this->imageInfo['ImageWidth']['value'] ?? 0);
            $height = (int) ($this->imageInfo['ImageHeight']['value'] ?? 0);

            if ($this->maxWidth !== null && $width > $this->maxWidth) {
                $this->failReason = 'image_too_wide';
                $fail($this->message('image_too_wide'));
                return;
            }

            if ($this->maxHeight !== null && $height > $this->maxHeight) {
                $this->failReason = 'image_too_tall';
                $fail($this->message('image_too_tall'));
                return;
            }

            if ($height > 0 && $width > 0) {
                $ratio = $width / $height;

                if ($this->exactAspectRatio !== null) {
                    $expected = $this->exactAspectRatio[0] / $this->exactAspectRatio[1];
                    if (abs($ratio - $expected) > 0.01) {
                        $this->failReason = 'aspect_ratio_mismatch';
                        $fail($this->message('aspect_ratio_mismatch'));
                        return;
                    }
                }

                if ($this->minAspectRatio !== null) {
                    $min = $this->minAspectRatio[0] / $this->minAspectRatio[1];
                    if ($ratio < $min - 0.01) {
                        $this->failReason = 'aspect_ratio_too_narrow';
                        $fail($this->message('aspect_ratio_too_narrow'));
                        return;
                    }
                }

                if ($this->maxAspectRatio !== null) {
                    $max = $this->maxAspectRatio[0] / $this->maxAspectRatio[1];
                    if ($ratio > $max + 0.01) {
                        $this->failReason = 'aspect_ratio_too_wide';
                        $fail($this->message('aspect_ratio_too_wide'));
                        return;
                    }
                }
            }
        }
    }

    // ==================== 查询方法 ====================

    public function fileAttributes(): ?FileAttributes { return $this->fileAttrs; }
    public function fileSize(): ?int { return $this->fileAttrs?->fileSize(); }
    public function mimeType(): ?string { return $this->fileAttrs?->mimeType(); }
    public function path(): ?string { return $this->parsedPath; }

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

    public function domainType(): ?string { return $this->detectedDomain; }
    public function failedReason(): ?string { return $this->failReason; }
    public function isCdnDomain(): bool { return $this->detectedDomain === OssAdapter::DOMAIN_CDN; }
    public function isUploadDomain(): bool { return $this->detectedDomain === OssAdapter::DOMAIN_UPLOAD; }
    public function isOssDomain(): bool { return $this->detectedDomain === OssAdapter::DOMAIN_OSS; }

    // ==================== 内部方法 ====================

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
        if ($adapter->isCdnUrl($url)) return OssAdapter::DOMAIN_CDN;
        if ($adapter->isUploadUrl($url)) return OssAdapter::DOMAIN_UPLOAD;
        if ($adapter->isOssUrl($url)) return OssAdapter::DOMAIN_OSS;
        if ($adapter->isOssInternalUrl($url)) return OssAdapter::DOMAIN_OSS_INTERNAL;
        return null;
    }

    protected function matchMimeType(string $mimeType, array $allowed): bool
    {
        foreach ($allowed as $pattern) {
            if ($mimeType === $pattern) return true;
            if (str_ends_with($pattern, '/*') && str_starts_with($mimeType, substr($pattern, 0, -1))) return true;
        }
        return false;
    }

    protected function fetchImageInfo(OssAdapter $adapter, string $path): ?array
    {
        try {
            $result = $adapter->client()->getObject(
                new Oss\Models\GetObjectRequest(
                    bucket: $adapter->bucket(),
                    key: $path,
                    process: 'image/info',
                )
            );

            $json = $result->body->getContents();
            $data = json_decode($json, true);
            return is_array($data) ? $data : null;
        } catch (Oss\Exception\OperationException) {
            return null;
        }
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
