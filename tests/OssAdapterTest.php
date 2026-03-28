<?php

namespace HughCube\Laravel\AliOSS\Tests;

use AlibabaCloud\Oss\V2 as Oss;
use HughCube\Laravel\AliOSS\OssAdapter;
use Illuminate\Support\Str;
use League\Flysystem\Config;
use League\Flysystem\Visibility;
use PHPUnit\Framework\Attributes\DataProvider;

class OssAdapterTest extends TestCase
{
    /**
     * 验证测试路径前缀的读写权限，确保 CI 环境凭据配置正确。
     */
    public function testTestPathPrefixIsReadWritable(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath('rw-check-' . Str::random(8) . '.txt');

        try {
            $adapter->write($path, 'rw-check');
            $this->assertTrue($adapter->fileExists($path));
            $this->assertSame('rw-check', $adapter->read($path));
        } finally {
            $adapter->delete($path);
        }
    }

    public function testClient(): void
    {
        $this->assertInstanceOf(Oss\Client::class, $this->getOssAdapter()->client());
    }

    public function testBucket(): void
    {
        $this->assertNotEmpty($this->getOssAdapter()->bucket());
    }

    public function testRegion(): void
    {
        $adapter = $this->createMockAdapter(['region' => 'cn-beijing']);
        $this->assertSame('cn-beijing', $adapter->region());

        $adapter2 = $this->createMockAdapter(['region' => null]);
        $this->assertNull($adapter2->region());
    }

    public function testCdnBaseUrl(): void
    {
        $adapter = $this->createMockAdapter(['cdnBaseUrl' => 'https://cdn.example.com']);
        $this->assertSame('https://cdn.example.com', $adapter->cdnBaseUrl());

        $adapter2 = $this->createMockAdapter(['cdnBaseUrl' => null]);
        $this->assertNull($adapter2->cdnBaseUrl());
    }

    public function testDomains(): void
    {
        $adapter = $this->createMockAdapter([
            'bucket' => 'test-bucket',
            'region' => 'cn-hangzhou',
            'cdnBaseUrl' => 'https://cdn.example.com',
            'uploadBaseUrl' => 'https://upload.example.com',
        ]);

        $this->assertSame('cdn.example.com', $adapter->cdnDomain());
        $this->assertSame('upload.example.com', $adapter->uploadDomain());
        $this->assertSame('test-bucket.oss-cn-hangzhou.aliyuncs.com', $adapter->ossDomain());
        $this->assertSame('test-bucket.oss-cn-hangzhou-internal.aliyuncs.com', $adapter->ossInternalDomain());
    }

    public function testWithConfig(): void
    {
        $adapter = $this->getOssAdapter();
        $new = $adapter->withConfig(['prefix' => 'new']);
        $this->assertNotSame($adapter, $new);
    }

    public function testWithBucket(): void
    {
        $new = $this->getOssAdapter()->withBucket('new-bucket');
        $this->assertSame('new-bucket', $new->bucket());
    }

    // ==================== URL 构建 ====================

    public function testUrlMethods(): void
    {
        $adapter = $this->createMockAdapter([
            'bucket' => 'test-bucket',
            'region' => 'cn-hangzhou',
            'cdnBaseUrl' => 'https://cdn.example.com',
            'uploadBaseUrl' => 'https://upload.example.com',
        ]);

        $this->assertSame('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/file.jpg', (string) $adapter->url('file.jpg'));
        $this->assertSame('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/file.jpg', (string) $adapter->ossUrl('file.jpg'));
        $this->assertSame('https://cdn.example.com/file.jpg', (string) $adapter->cdnUrl('file.jpg'));
        $this->assertSame('https://upload.example.com/file.jpg', (string) $adapter->uploadUrl('file.jpg'));
        $this->assertSame('https://test-bucket.oss-cn-hangzhou-internal.aliyuncs.com/file.jpg', (string) $adapter->ossInternalUrl('file.jpg'));
    }

    public function testCdnUrlReturnsNullWhenNotConfigured(): void
    {
        $adapter = $this->createMockAdapter(['cdnBaseUrl' => null]);
        $this->assertNull($adapter->cdnUrl('file.jpg'));
    }

    public function testUploadUrlReturnsNullWhenNotConfigured(): void
    {
        $adapter = $this->createMockAdapter(['uploadBaseUrl' => null]);
        $this->assertNull($adapter->uploadUrl('file.jpg'));
    }

    public function testUrlWithPrefix(): void
    {
        $adapter = $this->createMockAdapter([
            'bucket' => 'test-bucket',
            'region' => 'cn-hangzhou',
            'prefix' => 'pre',
            'cdnBaseUrl' => 'https://cdn.example.com',
        ]);

        $this->assertSame('https://cdn.example.com/pre/file.jpg', (string) $adapter->cdnUrl('file.jpg'));
        $this->assertSame('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/pre/file.jpg', (string) $adapter->ossUrl('file.jpg'));
    }

    public function testSignUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->signUrl($this->testPath('file.jpg'), 60);
        $this->assertStringContainsString('x-oss-signature', (string) $url);
    }

    public function testSignUploadUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->signUploadUrl($this->testPath('file.jpg'), 60);
        $this->assertStringContainsString('x-oss-signature', (string) $url);
    }

    public function testPresign(): void
    {
        $adapter = $this->getOssAdapter();
        $result = $adapter->presign($this->testPath('file.jpg'), 60, 'GET');
        $this->assertInstanceOf(Oss\Models\PresignResult::class, $result);
        $this->assertNotEmpty($result->url);
        $this->assertNotEmpty($result->method);
    }

    // ==================== URL 转换 ====================

    public function testUrlConversion(): void
    {
        $adapter = $this->createMockAdapter([
            'bucket' => 'test-bucket',
            'region' => 'cn-hangzhou',
            'cdnBaseUrl' => 'https://cdn.example.com',
            'uploadBaseUrl' => 'https://upload.example.com',
        ]);

        $cdn = 'https://cdn.example.com/path/file.jpg';
        $oss = $adapter->toOssUrl($cdn);
        $this->assertStringContainsString('test-bucket.oss-cn-hangzhou.aliyuncs.com', (string) $oss);
        $this->assertStringContainsString('/path/file.jpg', (string) $oss);

        $back = $adapter->toCdnUrl((string) $oss);
        $this->assertStringContainsString('cdn.example.com', (string) $back);
        $this->assertStringContainsString('/path/file.jpg', (string) $back);

        $upload = $adapter->toUploadUrl($cdn);
        $this->assertStringContainsString('upload.example.com', (string) $upload);

        $internal = $adapter->toOssInternalUrl($cdn);
        $this->assertStringContainsString('oss-cn-hangzhou-internal', (string) $internal);
    }

    public function testToCdnUrlReturnsNullWhenNotConfigured(): void
    {
        $adapter = $this->createMockAdapter(['cdnBaseUrl' => null]);
        $this->assertNull($adapter->toCdnUrl('https://example.com/file.jpg'));
    }

    // ==================== URL 识别 ====================

    #[DataProvider('urlDetectionProvider')]
    public function testUrlDetection(string $method, string $url, bool $expected, array $config = []): void
    {
        $adapter = $this->createMockAdapter(array_merge([
            'bucket' => 'test-bucket',
            'region' => 'cn-hangzhou',
            'cdnBaseUrl' => 'https://cdn.example.com',
            'uploadBaseUrl' => 'https://upload.example.com',
        ], $config));

        $this->assertSame($expected, $adapter->$method($url));
    }

    public static function urlDetectionProvider(): array
    {
        return [
            ['isCdnUrl', 'https://cdn.example.com/file.jpg', true],
            ['isCdnUrl', 'https://other.com/file.jpg', false],
            ['isUploadUrl', 'https://upload.example.com/file.jpg', true],
            ['isUploadUrl', 'https://other.com/file.jpg', false],
            ['isOssUrl', 'https://test-bucket.oss-cn-hangzhou.aliyuncs.com/file.jpg', true],
            ['isOssUrl', 'https://other.com/file.jpg', false],
            ['isOssInternalUrl', 'https://test-bucket.oss-cn-hangzhou-internal.aliyuncs.com/file.jpg', true],
            ['isBucketUrl', 'https://cdn.example.com/file.jpg', true],
            ['isBucketUrl', 'https://test-bucket.oss-cn-hangzhou.aliyuncs.com/file.jpg', true],
            ['isBucketUrl', 'https://other.com/file.jpg', false],
            ['isBucketUrl', 'not-a-url', false],
        ];
    }

    // ==================== Flysystem 操作 ====================

    public function testWriteAndRead(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath(Str::random(32) . '.txt');
        $content = Str::random();

        try {
            $adapter->write($path, $content);
            $this->assertTrue($adapter->fileExists($path));
            $this->assertSame($content, $adapter->read($path));
        } finally {
            $adapter->delete($path);
        }
    }

    public function testWriteStreamAndReadStream(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath(Str::random(32) . '.txt');
        $content = Str::random();

        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $content);
        rewind($stream);

        try {
            $adapter->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $readStream = $adapter->readStream($path);
            $this->assertIsResource($readStream);
            $this->assertSame($content, stream_get_contents($readStream));
            fclose($readStream);
        } finally {
            $adapter->delete($path);
        }
    }

    public function testCopyAndMove(): void
    {
        $adapter = $this->getOssAdapter();
        $source = $this->testPath(Str::random(32) . '.txt');
        $copyDest = $this->testPath(Str::random(32) . '-copy.txt');
        $moveDest = $this->testPath(Str::random(32) . '-move.txt');

        try {
            $adapter->write($source, 'content');
            $adapter->copy($source, $copyDest);
            $this->assertTrue($adapter->fileExists($copyDest));

            $adapter->move($source, $moveDest);
            $this->assertFalse($adapter->fileExists($source));
            $this->assertTrue($adapter->fileExists($moveDest));
        } finally {
            foreach ([$source, $copyDest, $moveDest] as $p) {
                try {
                    $adapter->delete($p);
                } catch (\Throwable $e) {
                }
            }
        }
    }

    public function testFileAttributes(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath(Str::random(32) . '.txt');
        $content = Str::random(50);

        try {
            $adapter->write($path, $content);
            $attrs = $adapter->fileAttributes($path);
            $this->assertSame(strlen($content), $attrs->fileSize());
            $this->assertNotNull($attrs->mimeType());
            $this->assertNotNull($attrs->lastModified());
        } finally {
            $adapter->delete($path);
        }
    }

    public function testVisibility(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath(Str::random(32) . '.txt');

        try {
            $adapter->write($path, 'content');

            foreach ([Visibility::PUBLIC, Visibility::PRIVATE] as $v) {
                $adapter->setVisibility($path, $v);
                $this->assertSame($v, $adapter->visibility($path)->visibility());
            }
        } finally {
            $adapter->delete($path);
        }
    }

    public function testCreateDirectory(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath('dir-' . Str::random(16));

        try {
            $adapter->createDirectory($path);
            $this->assertTrue($adapter->fileExists($path . '/'));
        } finally {
            $adapter->delete($path . '/');
        }
    }

    public function testDirectoryExists(): void
    {
        $this->assertTrue($this->getOssAdapter()->directoryExists('any'));
    }

    public function testDeleteDirectoryThrows(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->getOssAdapter()->deleteDirectory('any');
    }

    public function testListContentsThrows(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->getOssAdapter()->listContents('any', false);
    }

    // ==================== 扩展操作 ====================

    public function testWriteFile(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath(Str::random(32) . '.txt');
        $tmpFile = sys_get_temp_dir() . '/' . Str::random(16) . '.txt';
        file_put_contents($tmpFile, 'test content');

        try {
            $url = $adapter->writeFile($tmpFile, $path);
            $this->assertStringContainsString($path, (string) $url);
            $this->assertSame('test content', $adapter->read($path));
        } finally {
            $adapter->delete($path);
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    public function testDownload(): void
    {
        $adapter = $this->getOssAdapter();
        $path = $this->testPath(Str::random(32) . '.txt');
        $content = Str::random(100);

        $tmpFile = sys_get_temp_dir() . '/' . Str::random(16) . '.txt';
        try {
            $adapter->write($path, $content);
            $adapter->download($path, $tmpFile);
            $this->assertSame($content, file_get_contents($tmpFile));
        } finally {
            $adapter->delete($path);
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    public function testWatermarkText(): void
    {
        $encoded = OssAdapter::watermarkText('水印文字');
        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $decoded = base64_decode(strtr($encoded, ['-' => '+', '_' => '/']));
        $this->assertSame('水印文字', $decoded);
    }

    public function testNoOverwrite(): void
    {
        $options = $this->getOssAdapter()->noOverwrite();
        $this->assertInstanceOf(Config::class, $options);
        $this->assertTrue($options->get('forbidOverwrite'));
    }

    public function testPrefixer(): void
    {
        $adapter = $this->createMockAdapter(['prefix' => 'my-prefix']);
        $this->assertSame('my-prefix/test.txt', $adapter->prefixer()->prefixPath('test.txt'));

        $adapter2 = $this->createMockAdapter(['prefix' => '']);
        $this->assertSame('test.txt', $adapter2->prefixer()->prefixPath('test.txt'));
    }
}
