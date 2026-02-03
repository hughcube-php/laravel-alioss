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

    // ==================== 文档类型快捷方法测试 ====================

    public function testWordMimeType(): void
    {
        $rule = OssUrlExists::make()->word();

        $reflection = new \ReflectionClass($rule);
        $property = $reflection->getProperty('allowedMimeTypes');
        $property->setAccessible(true);

        $mimeTypes = $property->getValue($rule);

        $this->assertContains('application/msword', $mimeTypes);
        $this->assertContains('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $mimeTypes);
        $this->assertCount(2, $mimeTypes);
    }

    public function testPptMimeType(): void
    {
        $rule = OssUrlExists::make()->ppt();

        $reflection = new \ReflectionClass($rule);
        $property = $reflection->getProperty('allowedMimeTypes');
        $property->setAccessible(true);

        $mimeTypes = $property->getValue($rule);

        $this->assertContains('application/vnd.ms-powerpoint', $mimeTypes);
        $this->assertContains('application/vnd.openxmlformats-officedocument.presentationml.presentation', $mimeTypes);
        $this->assertCount(2, $mimeTypes);
    }

    public function testExcelMimeType(): void
    {
        $rule = OssUrlExists::make()->excel();

        $reflection = new \ReflectionClass($rule);
        $property = $reflection->getProperty('allowedMimeTypes');
        $property->setAccessible(true);

        $mimeTypes = $property->getValue($rule);

        $this->assertContains('application/vnd.ms-excel', $mimeTypes);
        $this->assertContains('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $mimeTypes);
        $this->assertCount(2, $mimeTypes);
    }

    public function testPdfMimeType(): void
    {
        $rule = OssUrlExists::make()->pdf();

        $reflection = new \ReflectionClass($rule);
        $property = $reflection->getProperty('allowedMimeTypes');
        $property->setAccessible(true);

        $mimeTypes = $property->getValue($rule);

        $this->assertContains('application/pdf', $mimeTypes);
        $this->assertCount(1, $mimeTypes);
    }

    public function testDocumentMimeType(): void
    {
        $rule = OssUrlExists::make()->document();

        $reflection = new \ReflectionClass($rule);
        $property = $reflection->getProperty('allowedMimeTypes');
        $property->setAccessible(true);

        $mimeTypes = $property->getValue($rule);

        $this->assertContains('application/pdf', $mimeTypes);
        $this->assertContains('application/msword', $mimeTypes);
        $this->assertContains('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $mimeTypes);
        $this->assertContains('application/vnd.ms-excel', $mimeTypes);
        $this->assertContains('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $mimeTypes);
        $this->assertContains('application/vnd.ms-powerpoint', $mimeTypes);
        $this->assertContains('application/vnd.openxmlformats-officedocument.presentationml.presentation', $mimeTypes);
        $this->assertContains('text/plain', $mimeTypes);
        $this->assertContains('application/rtf', $mimeTypes);
    }

    public function testArchiveMimeType(): void
    {
        $rule = OssUrlExists::make()->archive();

        $reflection = new \ReflectionClass($rule);
        $property = $reflection->getProperty('allowedMimeTypes');
        $property->setAccessible(true);

        $mimeTypes = $property->getValue($rule);

        $this->assertContains('application/zip', $mimeTypes);
        $this->assertContains('application/x-rar-compressed', $mimeTypes);
        $this->assertContains('application/vnd.rar', $mimeTypes);
        $this->assertContains('application/x-7z-compressed', $mimeTypes);
        $this->assertContains('application/gzip', $mimeTypes);
        $this->assertContains('application/x-tar', $mimeTypes);
        $this->assertContains('application/x-bzip2', $mimeTypes);
    }

    public function testTextMimeType(): void
    {
        $rule = OssUrlExists::make()->text();

        $reflection = new \ReflectionClass($rule);
        $property = $reflection->getProperty('allowedMimeTypes');
        $property->setAccessible(true);

        $mimeTypes = $property->getValue($rule);

        $this->assertContains('text/*', $mimeTypes);
    }

    public function testJsonMimeType(): void
    {
        $rule = OssUrlExists::make()->json();

        $reflection = new \ReflectionClass($rule);
        $property = $reflection->getProperty('allowedMimeTypes');
        $property->setAccessible(true);

        $mimeTypes = $property->getValue($rule);

        $this->assertContains('application/json', $mimeTypes);
        $this->assertCount(1, $mimeTypes);
    }

    public function testXmlMimeType(): void
    {
        $rule = OssUrlExists::make()->xml();

        $reflection = new \ReflectionClass($rule);
        $property = $reflection->getProperty('allowedMimeTypes');
        $property->setAccessible(true);

        $mimeTypes = $property->getValue($rule);

        $this->assertContains('application/xml', $mimeTypes);
        $this->assertContains('text/xml', $mimeTypes);
        $this->assertCount(2, $mimeTypes);
    }

    public function testMediaMimeType(): void
    {
        $rule = OssUrlExists::make()->media();

        $reflection = new \ReflectionClass($rule);
        $property = $reflection->getProperty('allowedMimeTypes');
        $property->setAccessible(true);

        $mimeTypes = $property->getValue($rule);

        $this->assertContains('image/*', $mimeTypes);
        $this->assertContains('video/*', $mimeTypes);
        $this->assertContains('audio/*', $mimeTypes);
        $this->assertCount(3, $mimeTypes);
    }

    // ==================== 扩展名验证测试 ====================

    public function testExtensionsWhitelistPass(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->extensions(['txt', 'log', 'md']);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        $adapter->delete($path);
    }

    public function testExtensionsWhitelistFail(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->extensions(['jpg', 'png', 'gif']);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('extension_not_allowed', $rule->getFailedReason());

        $adapter->delete($path);
    }

    public function testExtensionsCaseInsensitive(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'test/' . Str::random(32) . '.TXT';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->extensions(['txt']);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        $adapter->delete($path);
    }

    public function testExceptExtensionsPass(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->exceptExtensions(['exe', 'php', 'sh']);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        $adapter->delete($path);
    }

    public function testExceptExtensionsFail(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'test/' . Str::random(32) . '.php';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->exceptExtensions(['exe', 'php', 'sh']);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('extension_forbidden', $rule->getFailedReason());

        $adapter->delete($path);
    }

    // ==================== 目录验证测试 ====================

    public function testDirectoryWhitelistPass(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'uploads/images/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->directory('uploads/images');
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        $adapter->delete($path);
    }

    public function testDirectoryWhitelistFail(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'other/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->directory('uploads');
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('directory_not_allowed', $rule->getFailedReason());

        $adapter->delete($path);
    }

    public function testDirectoryWithLeadingSlash(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'uploads/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        // 使用带前导斜杠的目录
        $rule = OssUrlExists::make()->directory('/uploads/');
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        $adapter->delete($path);
    }

    public function testDirectoriesWhitelistPass(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'images/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->directories(['uploads', 'images', 'files']);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        $adapter->delete($path);
    }

    public function testExceptDirectoryPass(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'public/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->exceptDirectory('private');
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        $adapter->delete($path);
    }

    public function testExceptDirectoryFail(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'private/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->exceptDirectory('private');
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('directory_forbidden', $rule->getFailedReason());

        $adapter->delete($path);
    }

    public function testExceptDirectoriesFail(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'temp/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->exceptDirectories(['private', 'temp', 'cache']);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('directory_forbidden', $rule->getFailedReason());

        $adapter->delete($path);
    }

    // ==================== 路径正则匹配测试 ====================

    public function testPathMatchesPass(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'uploads/2024/01/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->pathMatches('/^uploads\/\d{4}\/\d{2}\//');
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        $adapter->delete($path);
    }

    public function testPathMatchesFail(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'other/' . Str::random(32) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->pathMatches('/^uploads\/\d{4}\//');
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('path_pattern_mismatch', $rule->getFailedReason());

        $adapter->delete($path);
    }

    // ==================== 文件名正则匹配测试 ====================

    public function testFilenameMatchesPass(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'test/123_avatar.jpg';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->filenameMatches('/^\d+_\w+\.\w+$/');
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        $adapter->delete($path);
    }

    public function testFilenameMatchesFail(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'test/invalid-name.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->filenameMatches('/^\d+_\w+\.\w+$/');
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('filename_pattern_mismatch', $rule->getFailedReason());

        $adapter->delete($path);
    }

    // ==================== 文件名长度测试 ====================

    public function testFilenameMaxLengthPass(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'test/short.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->filenameMaxLength(50);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        $adapter->delete($path);
    }

    public function testFilenameMaxLengthFail(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $longName = Str::random(60) . '.txt';
        $path = 'test/' . $longName;
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->filenameMaxLength(50);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('filename_too_long', $rule->getFailedReason());

        $adapter->delete($path);
    }

    // ==================== 路径信息获取测试 ====================

    public function testGetPathAfterValidation(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'uploads/images/test.jpg';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = new OssUrlExists();
        $rule->validate('url', $url, function ($message) {});

        // 路径可能包含 prefix
        $this->assertNotNull($rule->getPath());
        $this->assertStringContainsString('test.jpg', $rule->getPath());

        $adapter->delete($path);
    }

    public function testGetFilenameAfterValidation(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'uploads/images/myfile.jpg';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = new OssUrlExists();
        $rule->validate('url', $url, function ($message) {});

        $this->assertSame('myfile.jpg', $rule->getFilename());

        $adapter->delete($path);
    }

    public function testGetExtensionAfterValidation(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'uploads/document.pdf';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = new OssUrlExists();
        $rule->validate('url', $url, function ($message) {});

        $this->assertSame('pdf', $rule->getExtension());

        $adapter->delete($path);
    }

    public function testGetDirectoryAfterValidation(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'uploads/images/photo.jpg';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = new OssUrlExists();
        $rule->validate('url', $url, function ($message) {});

        $this->assertNotNull($rule->getDirectory());
        $this->assertStringContainsString('uploads', $rule->getDirectory());

        $adapter->delete($path);
    }

    // ==================== 组合验证测试 ====================

    public function testCombinedConstraintsPass(): void
    {
        $adapter = $this->getOssAdapter();
        $content = str_repeat('x', 50);
        $path = 'uploads/2024/' . Str::random(10) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()
            ->directory('uploads')
            ->extensions(['txt', 'log'])
            ->exceptExtensions(['exe', 'php'])
            ->filenameMaxLength(100)
            ->sizeBetween(10, 100)
            ->mimeTypes(['text/*']);

        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        $adapter->delete($path);
    }

    public function testCombinedConstraintsFailOnExtension(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'uploads/' . Str::random(10) . '.exe';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()
            ->directory('uploads')
            ->exceptExtensions(['exe', 'php']);

        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('extension_forbidden', $rule->getFailedReason());

        $adapter->delete($path);
    }

    public function testCombinedConstraintsFailOnDirectory(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'private/' . Str::random(10) . '.txt';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()
            ->directory('uploads')
            ->extensions(['txt']);

        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('directory_not_allowed', $rule->getFailedReason());

        $adapter->delete($path);
    }

    // ==================== 边界情况测试 ====================

    public function testEmptyPath(): void
    {
        $adapter = $this->getOssAdapter();
        $baseUrl = $adapter->getCdnBaseUrl() ?: $adapter->getUploadBaseUrl();

        $rule = new OssUrlExists();
        $failed = false;

        $rule->validate('url', $baseUrl, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertSame('invalid_path', $rule->getFailedReason());
    }

    public function testPathInfoMethodsReturnNullBeforeValidation(): void
    {
        $rule = new OssUrlExists();

        $this->assertNull($rule->getPath());
        $this->assertNull($rule->getFilename());
        $this->assertNull($rule->getExtension());
        $this->assertNull($rule->getDirectory());
    }

    public function testFileWithoutExtension(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'test/noextension';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::make()->extensions(['txt']);
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        // 没有扩展名的文件应该无法匹配 'txt' 扩展名
        $this->assertTrue($failed);
        $this->assertSame('extension_not_allowed', $rule->getFailedReason());

        $adapter->delete($path);
    }

    public function testNestedDirectoryValidation(): void
    {
        $adapter = $this->getOssAdapter();
        $content = 'test';
        $path = 'uploads/images/2024/01/photo.jpg';
        $adapter->write($path, $content, new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        // 子目录应该匹配父目录规则
        $rule = OssUrlExists::make()->directory('uploads');
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);

        $adapter->delete($path);
    }

    #[DataProvider('mimeTypeMethodsProvider')]
    public function testMimeTypeMethodsSetCorrectTypes(string $method, array $expectedTypes): void
    {
        $rule = OssUrlExists::make()->$method();

        $reflection = new \ReflectionClass($rule);
        $property = $reflection->getProperty('allowedMimeTypes');
        $property->setAccessible(true);

        $mimeTypes = $property->getValue($rule);

        foreach ($expectedTypes as $expected) {
            $this->assertContains($expected, $mimeTypes);
        }
    }

    public static function mimeTypeMethodsProvider(): array
    {
        return [
            'image' => ['image', ['image/*']],
            'video' => ['video', ['video/*']],
            'audio' => ['audio', ['audio/*']],
            'text' => ['text', ['text/*']],
            'media' => ['media', ['image/*', 'video/*', 'audio/*']],
        ];
    }
}
