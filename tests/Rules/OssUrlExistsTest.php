<?php

namespace HughCube\Laravel\AliOSS\Tests\Rules;

use HughCube\Laravel\AliOSS\OssAdapter;
use HughCube\Laravel\AliOSS\Rules\OssUrlExists;
use HughCube\Laravel\AliOSS\Tests\TestCase;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

class OssUrlExistsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testValidationPassesWithValidUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, 'content', new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $validator = Validator::make(['url' => $url], ['url' => new OssUrlExists()]);
        $this->assertTrue($validator->passes());

        // 清理
        $adapter->delete($path);
    }

    public function testValidationFailsWithNonExistentFile(): void
    {
        $adapter = $this->getOssAdapter();
        $url = ($adapter->getCdnBaseUrl() ?: $adapter->getUploadBaseUrl()) . '/nonexistent-' . Str::random(32) . '.jpg';

        $validator = Validator::make(['url' => $url], ['url' => new OssUrlExists()]);
        $this->assertFalse($validator->passes());
    }

    public function testValidationFailsWithInvalidDomain(): void
    {
        $validator = Validator::make(
            ['url' => 'https://other.example.com/file.jpg'],
            ['url' => new OssUrlExists()]
        );
        $this->assertFalse($validator->passes());
    }

    public function testValidationPassesWithEmptyStringWhenNotRequired(): void
    {
        // 空字符串在没有 required 规则时会跳过验证
        $validator = Validator::make(['url' => ''], ['url' => new OssUrlExists()]);
        $this->assertTrue($validator->passes());
    }

    public function testValidationFailsWithEmptyStringWhenRequired(): void
    {
        // 配合 required 规则使用时，空字符串会失败
        $validator = Validator::make(['url' => ''], ['url' => ['required', new OssUrlExists()]]);
        $this->assertFalse($validator->passes());
    }

    public function testValidationFailsWithNullValue(): void
    {
        $validator = Validator::make(['url' => null], ['url' => new OssUrlExists()]);
        $this->assertFalse($validator->passes());
    }

    public function testValidationFailsWithNonStringValue(): void
    {
        $validator = Validator::make(['url' => 123], ['url' => new OssUrlExists()]);
        $this->assertFalse($validator->passes());
    }

    public function testValidationFailsWithInvalidUrl(): void
    {
        $validator = Validator::make(['url' => 'not-a-url'], ['url' => new OssUrlExists()]);
        $this->assertFalse($validator->passes());
    }

    public function testDomainOnlyValidation(): void
    {
        $adapter = $this->getOssAdapter();
        $url = ($adapter->getCdnBaseUrl() ?: $adapter->getUploadBaseUrl()) . '/any-file.jpg';

        $rule = OssUrlExists::make()->domainOnly();
        $validator = Validator::make(['url' => $url], ['url' => $rule]);
        $this->assertTrue($validator->passes());
    }

    public function testDomainOnlyValidationFailsWithInvalidDomain(): void
    {
        $rule = OssUrlExists::make()->domainOnly();
        $validator = Validator::make(
            ['url' => 'https://other.example.com/file.jpg'],
            ['url' => $rule]
        );
        $this->assertFalse($validator->passes());
    }

    public function testCheckExistsValidation(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, 'content', new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->checkExists(true);
        $validator = Validator::make(['url' => $url], ['url' => $rule]);
        $this->assertTrue($validator->passes());

        // 清理
        $adapter->delete($path);
    }

    public function testGetFailedReason(): void
    {
        $rule = new OssUrlExists();
        $failed = false;

        $rule->validate('url', 'https://other.example.com/file.jpg', function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('domain_mismatch', $rule->getFailedReason());
    }

    public function testGetFailedReasonForInvalidUrl(): void
    {
        $rule = new OssUrlExists();
        $failed = false;

        $rule->validate('url', 'not-a-url', function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('invalid_url', $rule->getFailedReason());
    }

    public function testGetFailedReasonForFileNotFound(): void
    {
        $adapter = $this->getOssAdapter();
        $url = ($adapter->getCdnBaseUrl() ?: $adapter->getUploadBaseUrl()) . '/nonexistent-' . Str::random(32) . '.jpg';

        $rule = new OssUrlExists();
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('file_not_found', $rule->getFailedReason());
    }

    #[DataProvider('invalidUrlsProvider')]
    public function testInvalidUrlsFail(mixed $url): void
    {
        $validator = Validator::make(['url' => $url], ['url' => new OssUrlExists()]);
        $this->assertFalse($validator->passes());
    }

    public static function invalidUrlsProvider(): array
    {
        return [
            // 注意：空字符串 '' 在没有 required 规则时会跳过验证，所以不在此列表中
            'null' => [null],
            'integer' => [123],
            'array' => [['url' => 'test']],
            'invalid url string' => ['not-a-valid-url'],
            'different domain' => ['https://other.example.com/file.jpg'],
        ];
    }

    public function testGetFileAttributesAfterValidation(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test content for file attributes';
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = new OssUrlExists();
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
        $this->assertNotNull($rule->getFileAttributes());
        $this->assertSame(strlen($content), $rule->getFileSize());
        $this->assertNotNull($rule->getMimeType());

        // 清理
        $adapter->delete($path);
    }

    public function testGetDetectedDomainType(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $rule = OssUrlExists::make();
        $failed = false;

        // 使用 CDN URL
        if ($adapter->getCdnBaseUrl()) {
            $cdnUrl = $adapter->cdnUrl($path);
            $rule->validate('url', $cdnUrl, function ($message) use (&$failed) {
                $failed = true;
            });

            $this->assertFalse($failed);
            $this->assertSame(OssUrlExists::DOMAIN_CDN, $rule->getDetectedDomainType());
            $this->assertTrue($rule->isCdnDomain());
            $this->assertFalse($rule->isUploadDomain());
            $this->assertFalse($rule->isOssDomain());
        }

        // 清理
        $adapter->delete($path);
    }

    public function testCdnDomainConstraint(): void
    {
        $adapter = $this->getOssAdapter();

        if (!$adapter->getCdnBaseUrl()) {
            $this->markTestSkipped('CDN base URL not configured');
        }

        $content = 'test';
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        // CDN URL 应该通过 cdnDomain() 约束
        $cdnUrl = $adapter->cdnUrl($path);
        $rule = OssUrlExists::make()->cdnDomain();
        $failed = false;

        $rule->validate('url', $cdnUrl, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        // 清理
        $adapter->delete($path);
    }

    public function testUploadDomainConstraint(): void
    {
        $adapter = $this->getOssAdapter();

        if (!$adapter->getUploadBaseUrl()) {
            $this->markTestSkipped('Upload base URL not configured');
        }

        $content = 'test';
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        // 构造 upload URL
        $uploadUrl = rtrim($adapter->getUploadBaseUrl(), '/') . '/' . $adapter->getPrefixer()->prefixPath($path);
        $rule = OssUrlExists::make()->uploadDomain();
        $failed = false;

        $rule->validate('url', $uploadUrl, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        // 清理
        $adapter->delete($path);
    }

    public function testAllowedDomainsConstraint(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        // 允许 CDN 和 Upload 域名
        $rule = OssUrlExists::make()->allowedDomains([OssUrlExists::DOMAIN_CDN, OssUrlExists::DOMAIN_UPLOAD]);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        // 如果 URL 属于 CDN 或 Upload，应该通过
        $domainType = $rule->getDetectedDomainType();
        if (in_array($domainType, [OssUrlExists::DOMAIN_CDN, OssUrlExists::DOMAIN_UPLOAD])) {
            $this->assertFalse($failed);
        }

        // 清理
        $adapter->delete($path);
    }

    public function testMinSizeConstraint(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'small';
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        // 设置最小大小为 100 字节，文件应该太小
        $rule = OssUrlExists::make()->minSize(100);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('file_too_small', $rule->getFailedReason());

        // 清理
        $adapter->delete($path);
    }

    public function testMaxSizeConstraint(): void
    {
        $adapter = $this->getOssAdapter();
        $content = str_repeat('x', 100);
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        // 设置最大大小为 50 字节，文件应该太大
        $rule = OssUrlExists::make()->maxSize(50);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('file_too_large', $rule->getFailedReason());

        // 清理
        $adapter->delete($path);
    }

    public function testSizeBetweenConstraint(): void
    {
        $adapter = $this->getOssAdapter();
        $content = str_repeat('x', 50);
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        // 设置大小范围为 10-100 字节
        $rule = OssUrlExists::make()->sizeBetween(10, 100);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        // 清理
        $adapter->delete($path);
    }

    public function testMimeTypesConstraint(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test content';
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        // 只允许图片类型，文本文件应该失败
        $rule = OssUrlExists::make()->image();
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('mime_type_not_allowed', $rule->getFailedReason());

        // 清理
        $adapter->delete($path);
    }

    public function testMimeTypesConstraintWithWildcard(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test content';
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        // 允许 text/* 类型
        $rule = OssUrlExists::make()->mimeTypes(['text/*']);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        // 清理
        $adapter->delete($path);
    }

    public function testAnyDomainConstraint(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->anyDomain();
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        // 清理
        $adapter->delete($path);
    }

    public function testChainedConstraints(): void
    {
        $adapter = $this->getOssAdapter();
        $content = str_repeat('x', 50);
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        // 链式调用多个约束
        $rule = OssUrlExists::make()
            ->anyDomain()
            ->sizeBetween(10, 100)
            ->mimeTypes(['text/*', 'application/*']);

        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        // 清理
        $adapter->delete($path);
    }

    public function testDomainTypeNotAllowedReason(): void
    {
        $adapter = $this->getOssAdapter();

        if (!$adapter->getCdnBaseUrl()) {
            $this->markTestSkipped('CDN base URL not configured');
        }

        $content = 'test';
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        // 使用 CDN URL，但只允许 OSS 原始域名
        $cdnUrl = $adapter->cdnUrl($path);
        $rule = OssUrlExists::make()->ossDomain();
        $failed = false;

        $rule->validate('url', $cdnUrl, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('domain_type_not_allowed', $rule->getFailedReason());

        // 清理
        $adapter->delete($path);
    }
}
