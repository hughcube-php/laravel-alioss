<?php

namespace HughCube\Laravel\AliOSS\Rules;

use Closure;
use HughCube\Laravel\AliOSS\OssAdapter;
use HughCube\PUrl\HUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use OSS\Core\OssException;

class OssUrlExists implements ValidationRule
{
    protected ?string $disk;

    protected bool $checkBucketDomain;

    protected bool $checkFileExists;

    protected ?string $failedReason = null;

    /**
     * @param string|null $disk  OSS disk 名称，null 时使用默认的 'oss'
     * @param bool $checkBucketDomain 是否检查 URL 域名属于该 bucket
     * @param bool $checkFileExists 是否检查文件真实存在于 OSS
     */
    public function __construct(
        ?string $disk = null,
        bool $checkBucketDomain = true,
        bool $checkFileExists = true
    ) {
        $this->disk = $disk;
        $this->checkBucketDomain = $checkBucketDomain;
        $this->checkFileExists = $checkFileExists;
    }

    /**
     * 创建只检查域名归属的验证器
     */
    public static function domainOnly(?string $disk = null): static
    {
        return new static($disk, true, false);
    }

    /**
     * 创建只检查文件存在的验证器（跳过域名检查）
     */
    public static function existsOnly(?string $disk = null): static
    {
        return new static($disk, false, true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 验证是否是有效的 URL
        if (!HUrl::isUrlString($value)) {
            $this->failedReason = 'invalid_url';
            $fail($this->message('invalid_url'));
            return;
        }

        $adapter = $this->getAdapter();
        if (!$adapter instanceof OssAdapter) {
            $fail($this->message('invalid_disk'));
            return;
        }

        // 检查域名归属
        if ($this->checkBucketDomain && !$adapter->isBucketUrl($value)) {
            $this->failedReason = 'domain_mismatch';
            $fail($this->message('domain_mismatch'));
            return;
        }

        // 检查文件是否存在
        if ($this->checkFileExists) {
            try {
                if (!$adapter->isValidUrl($value, false, true)) {
                    $this->failedReason = 'file_not_found';
                    $fail($this->message('file_not_found'));
                    return;
                }
            } catch (OssException $e) {
                $this->failedReason = 'oss_error';
                $fail($this->message('oss_error'));
                return;
            }
        }
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
            'file_not_found' => 'The :attribute does not exist in OSS.',
            'oss_error' => 'The :attribute validation failed due to OSS error.',
        ];

        return $messages[$key] ?? 'The :attribute is invalid.';
    }

    public function getFailedReason(): ?string
    {
        return $this->failedReason;
    }
}
