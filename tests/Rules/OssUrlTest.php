<?php

namespace HughCube\Laravel\AliOSS\Tests\Rules;

use HughCube\Laravel\AliOSS\OssAdapter;
use HughCube\Laravel\AliOSS\Rules\OssUrl;
use HughCube\Laravel\AliOSS\Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use League\Flysystem\Config;

class OssUrlTest extends TestCase
{
    public function testValidationPassesWithValidUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, 'content');

        $url = $adapter->cdnUrl($path) ?? $adapter->url($path);
        $validator = Validator::make(['url' => $url], ['url' => new OssUrl()]);
        $this->assertTrue($validator->passes());

        $adapter->delete($path);
    }

    public function testValidationFailsWithNonExistentFile(): void
    {
        $adapter = $this->getOssAdapter();
        $url = ($adapter->cdnBaseUrl() ?? $adapter->uploadBaseUrl()) . '/nonexistent-' . Str::random(32) . '.jpg';
        $validator = Validator::make(['url' => $url], ['url' => new OssUrl()]);
        $this->assertFalse($validator->passes());
    }

    public function testValidationFailsWithInvalidUrl(): void
    {
        $validator = Validator::make(['url' => 'not-a-url'], ['url' => new OssUrl()]);
        $this->assertFalse($validator->passes());
    }

    public function testValidationFailsWithInvalidDomain(): void
    {
        $validator = Validator::make(['url' => 'https://other.example.com/file.jpg'], ['url' => new OssUrl()]);
        $this->assertFalse($validator->passes());
    }

    public function testDomainOnly(): void
    {
        $adapter = $this->getOssAdapter();
        $url = ($adapter->cdnBaseUrl() ?? $adapter->uploadBaseUrl()) . '/any-file.jpg';

        $rule = OssUrl::make()->domainOnly();
        $validator = Validator::make(['url' => $url], ['url' => $rule]);
        $this->assertTrue($validator->passes());
    }

    public function testCdnDomainConstraint(): void
    {
        $adapter = $this->getOssAdapter();
        if (!$adapter->cdnBaseUrl()) $this->markTestSkipped('No CDN configured');

        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, 'test');
        $cdnUrl = $adapter->cdnUrl($path);

        $rule = OssUrl::make()->cdnDomain();
        $failed = false;
        $rule->validate('url', $cdnUrl, function() use (&$failed) { $failed = true; });
        $this->assertFalse($failed);
        $this->assertTrue($rule->isCdnDomain());

        $adapter->delete($path);
    }

    public function testFailedReason(): void
    {
        $rule = new OssUrl();
        $rule->validate('url', 'not-a-url', function() {});
        $this->assertSame('invalid_url', $rule->failedReason());
    }

    public function testFailedReasonDomainMismatch(): void
    {
        $rule = new OssUrl();
        $rule->validate('url', 'https://other.example.com/file.jpg', function() {});
        $this->assertSame('domain_mismatch', $rule->failedReason());
    }

    public function testMinSizeConstraint(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, 'small');
        $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

        $rule = OssUrl::make()->minSize(100);
        $failed = false;
        $rule->validate('url', $url, function() use (&$failed) { $failed = true; });
        $this->assertTrue($failed);
        $this->assertSame('file_too_small', $rule->failedReason());

        $adapter->delete($path);
    }

    public function testMaxSizeConstraint(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, str_repeat('x', 100));
        $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

        $rule = OssUrl::make()->maxSize(50);
        $failed = false;
        $rule->validate('url', $url, function() use (&$failed) { $failed = true; });
        $this->assertTrue($failed);
        $this->assertSame('file_too_large', $rule->failedReason());

        $adapter->delete($path);
    }

    public function testExtensionsWhitelist(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';
        $adapter->write($path, 'test');
        $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

        $rule = OssUrl::make()->extensions(['jpg', 'png']);
        $failed = false;
        $rule->validate('url', $url, function() use (&$failed) { $failed = true; });
        $this->assertTrue($failed);
        $this->assertSame('extension_not_allowed', $rule->failedReason());

        $adapter->delete($path);
    }

    public function testExceptExtensions(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.php';
        $adapter->write($path, 'test');
        $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

        $rule = OssUrl::make()->exceptExtensions(['exe', 'php']);
        $failed = false;
        $rule->validate('url', $url, function() use (&$failed) { $failed = true; });
        $this->assertTrue($failed);
        $this->assertSame('extension_forbidden', $rule->failedReason());

        $adapter->delete($path);
    }

    public function testDirectoryConstraint(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'other/' . Str::random(32) . '.txt';
        $adapter->write($path, 'test');
        $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

        $rule = OssUrl::make()->directory('uploads');
        $failed = false;
        $rule->validate('url', $url, function() use (&$failed) { $failed = true; });
        $this->assertTrue($failed);
        $this->assertSame('directory_not_allowed', $rule->failedReason());

        $adapter->delete($path);
    }

    public function testExceptDirectory(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'private/' . Str::random(32) . '.txt';
        $adapter->write($path, 'test');
        $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

        $rule = OssUrl::make()->exceptDirectory('private');
        $failed = false;
        $rule->validate('url', $url, function() use (&$failed) { $failed = true; });
        $this->assertTrue($failed);
        $this->assertSame('directory_forbidden', $rule->failedReason());

        $adapter->delete($path);
    }

    public function testFilenameMaxLength(): void
    {
        $adapter = $this->getOssAdapter();
        $longName = Str::random(60) . '.txt';
        $path = 'test/' . $longName;
        $adapter->write($path, 'test');
        $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

        $rule = OssUrl::make()->filenameMaxLength(50);
        $failed = false;
        $rule->validate('url', $url, function() use (&$failed) { $failed = true; });
        $this->assertTrue($failed);
        $this->assertSame('filename_too_long', $rule->failedReason());

        $adapter->delete($path);
    }

    public function testQueryMethods(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'uploads/images/test.jpg';
        $adapter->write($path, 'test');
        $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

        $rule = new OssUrl();
        $rule->validate('url', $url, function() {});

        $this->assertNotNull($rule->path());
        $this->assertSame('test.jpg', $rule->filename());
        $this->assertSame('jpg', $rule->extension());
        $this->assertNotNull($rule->getDirectory());
        $this->assertNotNull($rule->domainType());
        $this->assertNotNull($rule->fileAttributes());

        $adapter->delete($path);
    }

    public function testQueryMethodsBeforeValidation(): void
    {
        $rule = new OssUrl();
        $this->assertNull($rule->path());
        $this->assertNull($rule->filename());
        $this->assertNull($rule->extension());
        $this->assertNull($rule->getDirectory());
        $this->assertNull($rule->domainType());
        $this->assertNull($rule->failedReason());
    }

    public function testMimeTypeMethods(): void
    {
        $rule = OssUrl::make()->image();
        $ref = new \ReflectionClass($rule);
        $prop = $ref->getProperty('allowedMimeTypes');
        $this->assertContains('image/*', $prop->getValue($rule));

        $rule2 = OssUrl::make()->document();
        $types = $ref->getProperty('allowedMimeTypes')->getValue($rule2);
        $this->assertContains('application/pdf', $types);
        $this->assertContains('application/msword', $types);
    }
}
