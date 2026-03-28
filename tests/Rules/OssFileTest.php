<?php

namespace HughCube\Laravel\AliOSS\Tests\Rules;

use HughCube\Laravel\AliOSS\OssAdapter;
use HughCube\Laravel\AliOSS\Rules\OssFile;
use HughCube\Laravel\AliOSS\Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use League\Flysystem\Config;

class OssFileTest extends TestCase
{
    public function testValidationPassesWithValidUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath(Str::random(32) . '.txt');

        try {
            $adapter->write($path, 'content');
            $url = $adapter->cdnUrl($path) ?? $adapter->url($path);
            $validator = Validator::make(['url' => $url], ['url' => new OssFile()]);
            $this->assertTrue($validator->passes());
        } finally {
            $adapter->delete($path);
        }
    }

    public function testValidationFailsWithNonExistentFile(): void
    {
        $adapter = $this->getOssAdapter();
        $prefix = $this->testPath('nonexistent-' . Str::random(32) . '.jpg');
        $url = ($adapter->cdnBaseUrl() ?? $adapter->uploadBaseUrl()) . '/' . $prefix;
        $validator = Validator::make(['url' => $url], ['url' => new OssFile()]);
        $this->assertFalse($validator->passes());
    }

    public function testValidationFailsWithInvalidUrl(): void
    {
        $validator = Validator::make(['url' => 'not-a-url'], ['url' => new OssFile()]);
        $this->assertFalse($validator->passes());
    }

    public function testValidationFailsWithInvalidDomain(): void
    {
        $validator = Validator::make(['url' => 'https://other.example.com/file.jpg'], ['url' => new OssFile()]);
        $this->assertFalse($validator->passes());
    }

    public function testDomainOnly(): void
    {
        $adapter = $this->getOssAdapter();
        $url = ($adapter->cdnBaseUrl() ?? $adapter->uploadBaseUrl()) . '/any-file.jpg';

        $rule = OssFile::make()->domainOnly();
        $validator = Validator::make(['url' => $url], ['url' => $rule]);
        $this->assertTrue($validator->passes());
    }

    public function testCdnDomainConstraint(): void
    {
        $adapter = $this->getOssAdapter();
        if (!$adapter->cdnBaseUrl()) {
            $this->markTestSkipped('No CDN configured');
        }

        $path = $this->testPath(Str::random(32) . '.txt');

        try {
            $adapter->write($path, 'test');
            $cdnUrl = $adapter->cdnUrl($path);

            $rule = OssFile::make()->cdnDomain();
            $failed = false;
            $rule->validate('url', $cdnUrl, function () use (&$failed) {
                $failed = true;
            });
            $this->assertFalse($failed);
            $this->assertTrue($rule->isCdnDomain());
        } finally {
            $adapter->delete($path);
        }
    }

    public function testFailedReason(): void
    {
        $rule = new OssFile();
        $rule->validate('url', 'not-a-url', function () {
        });
        $this->assertSame('invalid_url', $rule->failedReason());
    }

    public function testFailedReasonDomainMismatch(): void
    {
        $rule = new OssFile();
        $rule->validate('url', 'https://other.example.com/file.jpg', function () {
        });
        $this->assertSame('domain_mismatch', $rule->failedReason());
    }

    public function testMinSizeConstraint(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath(Str::random(32) . '.txt');

        try {
            $adapter->write($path, 'small');
            $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

            $rule = OssFile::make()->minSize(100);
            $failed = false;
            $rule->validate('url', $url, function () use (&$failed) {
                $failed = true;
            });
            $this->assertTrue($failed);
            $this->assertSame('file_too_small', $rule->failedReason());
        } finally {
            $adapter->delete($path);
        }
    }

    public function testMaxSizeConstraint(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath(Str::random(32) . '.txt');

        try {
            $adapter->write($path, str_repeat('x', 100));
            $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

            $rule = OssFile::make()->maxSize(50);
            $failed = false;
            $rule->validate('url', $url, function () use (&$failed) {
                $failed = true;
            });
            $this->assertTrue($failed);
            $this->assertSame('file_too_large', $rule->failedReason());
        } finally {
            $adapter->delete($path);
        }
    }

    public function testExtensionsWhitelist(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath(Str::random(32) . '.txt');

        try {
            $adapter->write($path, 'test');
            $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

            $rule = OssFile::make()->extensions(['jpg', 'png']);
            $failed = false;
            $rule->validate('url', $url, function () use (&$failed) {
                $failed = true;
            });
            $this->assertTrue($failed);
            $this->assertSame('extension_not_allowed', $rule->failedReason());
        } finally {
            $adapter->delete($path);
        }
    }

    public function testExceptExtensions(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath(Str::random(32) . '.php');

        try {
            $adapter->write($path, 'test');
            $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

            $rule = OssFile::make()->exceptExtensions(['exe', 'php']);
            $failed = false;
            $rule->validate('url', $url, function () use (&$failed) {
                $failed = true;
            });
            $this->assertTrue($failed);
            $this->assertSame('extension_forbidden', $rule->failedReason());
        } finally {
            $adapter->delete($path);
        }
    }

    public function testDirectoryConstraint(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath('other/' . Str::random(32) . '.txt');

        try {
            $adapter->write($path, 'test');
            $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

            $rule = OssFile::make()->directory('uploads');
            $failed = false;
            $rule->validate('url', $url, function () use (&$failed) {
                $failed = true;
            });
            $this->assertTrue($failed);
            $this->assertSame('directory_not_allowed', $rule->failedReason());
        } finally {
            $adapter->delete($path);
        }
    }

    public function testExceptDirectory(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath('private/' . Str::random(32) . '.txt');

        try {
            $adapter->write($path, 'test');
            $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

            $rule = OssFile::make()->exceptDirectory($this->testPath('private'));
            $failed = false;
            $rule->validate('url', $url, function () use (&$failed) {
                $failed = true;
            });
            $this->assertTrue($failed);
            $this->assertSame('directory_forbidden', $rule->failedReason());
        } finally {
            $adapter->delete($path);
        }
    }

    public function testFilenameMaxLength(): void
    {
        $adapter = $this->getOssAdapter();
        $longName = Str::random(60) . '.txt';
        $path = $this->testPath($longName);

        try {
            $adapter->write($path, 'test');
            $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

            $rule = OssFile::make()->filenameMaxLength(50);
            $failed = false;
            $rule->validate('url', $url, function () use (&$failed) {
                $failed = true;
            });
            $this->assertTrue($failed);
            $this->assertSame('filename_too_long', $rule->failedReason());
        } finally {
            $adapter->delete($path);
        }
    }

    public function testQueryMethods(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath('images/test.jpg');

        try {
            $adapter->write($path, 'test');
            $url = $adapter->cdnUrl($path) ?? $adapter->url($path);

            $rule = new OssFile();
            $rule->validate('url', $url, function () {
            });

            $this->assertNotNull($rule->path());
            $this->assertSame('test.jpg', $rule->filename());
            $this->assertSame('jpg', $rule->extension());
            $this->assertNotNull($rule->getDirectory());
            $this->assertNotNull($rule->domainType());
            $this->assertNotNull($rule->fileAttributes());
        } finally {
            $adapter->delete($path);
        }
    }

    public function testQueryMethodsBeforeValidation(): void
    {
        $rule = new OssFile();
        $this->assertNull($rule->path());
        $this->assertNull($rule->filename());
        $this->assertNull($rule->extension());
        $this->assertNull($rule->getDirectory());
        $this->assertNull($rule->domainType());
        $this->assertNull($rule->failedReason());
    }

    public function testMimeTypeMethods(): void
    {
        $rule = OssFile::make()->image();
        $ref = new \ReflectionClass($rule);
        $prop = $ref->getProperty('allowedMimeTypes');
        $this->assertContains('image/*', $prop->getValue($rule));

        $rule2 = OssFile::make()->document();
        $types = $ref->getProperty('allowedMimeTypes')->getValue($rule2);
        $this->assertContains('application/pdf', $types);
        $this->assertContains('application/msword', $types);
    }
}
