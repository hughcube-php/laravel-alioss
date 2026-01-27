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

        $rule = OssUrlExists::domainOnly();
        $validator = Validator::make(['url' => $url], ['url' => $rule]);
        $this->assertTrue($validator->passes());
    }

    public function testDomainOnlyValidationFailsWithInvalidDomain(): void
    {
        $rule = OssUrlExists::domainOnly();
        $validator = Validator::make(
            ['url' => 'https://other.example.com/file.jpg'],
            ['url' => $rule]
        );
        $this->assertFalse($validator->passes());
    }

    public function testExistsOnlyValidation(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, 'content', new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        $rule = OssUrlExists::existsOnly();
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

    public function testGetFailedReasonForFileNotFoundOrOssError(): void
    {
        $adapter = $this->getOssAdapter();
        $url = ($adapter->getCdnBaseUrl() ?: $adapter->getUploadBaseUrl()) . '/nonexistent-' . Str::random(32) . '.jpg';

        $rule = new OssUrlExists();
        $failed = false;

        $rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
        // 网络正常时返回 'file_not_found'，网络异常时返回 'oss_error'
        $this->assertContains($rule->getFailedReason(), ['file_not_found', 'oss_error']);
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
}
