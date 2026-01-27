<?php

namespace HughCube\Laravel\AliOSS\Tests;

use HughCube\Laravel\AliOSS\OssAdapter;
use Illuminate\Support\Str;
use League\Flysystem\Config;
use League\Flysystem\Visibility;
use OSS\OssClient;
use PHPUnit\Framework\Attributes\DataProvider;

class OssAdapterTest extends TestCase
{
    public function testGetOssClient(): void
    {
        $adapter = $this->getOssAdapter();
        $this->assertInstanceOf(OssClient::class, $adapter->getOssClient());
    }

    public function testGetBucket(): void
    {
        $adapter = $this->getOssAdapter();
        $this->assertNotEmpty($adapter->getBucket());
    }

    public function testGetRegionId(): void
    {
        $adapter = $this->getOssAdapter();
        $this->assertNotEmpty($adapter->getRegionId());
    }

    public function testGetCdnBaseUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $cdnBaseUrl = $adapter->getCdnBaseUrl();
        $this->assertTrue($cdnBaseUrl === null || is_string($cdnBaseUrl));
    }

    public function testGetUploadBaseUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $uploadBaseUrl = $adapter->getUploadBaseUrl();
        $this->assertTrue($uploadBaseUrl === null || is_string($uploadBaseUrl));
    }

    public function testGetOssOriginalDomain(): void
    {
        $adapter = $this->createMockAdapter([
            'bucket' => 'my-bucket',
            'regionId' => 'cn-shanghai',
        ]);

        $this->assertSame('my-bucket.oss-cn-shanghai.aliyuncs.com', $adapter->getOssOriginalDomain(false));
        $this->assertSame('my-bucket.oss-cn-shanghai-internal.aliyuncs.com', $adapter->getOssOriginalDomain(true));
    }

    public function testGetCdnDomain(): void
    {
        $adapter = $this->createMockAdapter(['cdnBaseUrl' => 'https://cdn.example.com/prefix']);
        $this->assertSame('cdn.example.com', $adapter->getCdnDomain());

        $adapter2 = $this->createMockAdapter(['cdnBaseUrl' => null]);
        $this->assertNull($adapter2->getCdnDomain());
    }

    public function testGetUploadDomain(): void
    {
        $adapter = $this->createMockAdapter(['uploadBaseUrl' => 'https://upload.example.com/prefix']);
        $this->assertSame('upload.example.com', $adapter->getUploadDomain());

        $adapter2 = $this->createMockAdapter(['uploadBaseUrl' => null]);
        $this->assertNull($adapter2->getUploadDomain());
    }

    public function testWithConfig(): void
    {
        $adapter = $this->getOssAdapter();
        $newAdapter = $adapter->withConfig(['prefix' => 'new-prefix']);

        $this->assertInstanceOf(OssAdapter::class, $newAdapter);
        $this->assertNotSame($adapter, $newAdapter);
    }

    public function testWithBucket(): void
    {
        $adapter = $this->getOssAdapter();
        $newAdapter = $adapter->withBucket('new-bucket');

        $this->assertInstanceOf(OssAdapter::class, $newAdapter);
        $this->assertSame('new-bucket', $newAdapter->getBucket());
    }

    public function testFileExistsAndWrite(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';
        $content = Str::random();

        $this->assertFalse($adapter->fileExists($path));

        $adapter->write($path, $content, new Config());
        $this->assertTrue($adapter->fileExists($path));

        // 清理
        $adapter->delete($path);
    }

    public function testWriteAndRead(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';
        $content = Str::random();

        $adapter->write($path, $content, new Config());
        $this->assertSame($content, $adapter->read($path));

        // 清理
        $adapter->delete($path);
    }

    public function testWriteStreamAndReadStream(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';
        $content = Str::random();

        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $content);
        rewind($stream);

        $adapter->writeStream($path, $stream, new Config());
        fclose($stream);

        $readStream = $adapter->readStream($path);
        $this->assertIsResource($readStream);
        $this->assertSame($content, stream_get_contents($readStream));
        fclose($readStream);

        // 清理
        $adapter->delete($path);
    }

    public function testDelete(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';

        $adapter->write($path, 'content', new Config());
        $this->assertTrue($adapter->fileExists($path));

        $adapter->delete($path);
        $this->assertFalse($adapter->fileExists($path));
    }

    public function testCopyAndMove(): void
    {
        $adapter = $this->getOssAdapter();
        $source = 'test/' . Str::random(32) . '.txt';
        $copyDest = 'test/' . Str::random(32) . '-copy.txt';
        $moveDest = 'test/' . Str::random(32) . '-move.txt';
        $content = Str::random();

        $adapter->write($source, $content, new Config());

        // 测试 copy
        $adapter->copy($source, $copyDest, new Config());
        $this->assertTrue($adapter->fileExists($source));
        $this->assertTrue($adapter->fileExists($copyDest));
        $this->assertSame($content, $adapter->read($copyDest));

        // 测试 move
        $adapter->move($source, $moveDest, new Config());
        $this->assertFalse($adapter->fileExists($source));
        $this->assertTrue($adapter->fileExists($moveDest));

        // 清理
        $adapter->delete($copyDest);
        $adapter->delete($moveDest);
    }

    public function testFileSize(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';
        $content = Str::random(100);

        $adapter->write($path, $content, new Config());

        $fileAttributes = $adapter->fileSize($path);
        $this->assertSame(strlen($content), $fileAttributes->fileSize());

        // 清理
        $adapter->delete($path);
    }

    public function testMimeType(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';

        $adapter->write($path, 'content', new Config());

        $fileAttributes = $adapter->mimeType($path);
        $this->assertSame('text/plain', $fileAttributes->mimeType());

        // 清理
        $adapter->delete($path);
    }

    public function testLastModified(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';

        $adapter->write($path, 'content', new Config());

        $fileAttributes = $adapter->lastModified($path);
        $this->assertIsInt($fileAttributes->lastModified());
        $this->assertGreaterThan(0, $fileAttributes->lastModified());

        // 清理
        $adapter->delete($path);
    }

    public function testSetVisibilityAndVisibility(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';

        $adapter->write($path, 'content', new Config());

        foreach ([Visibility::PUBLIC, Visibility::PRIVATE] as $visibility) {
            $adapter->setVisibility($path, $visibility);
            $fileAttributes = $adapter->visibility($path);
            $this->assertSame($visibility, $fileAttributes->visibility());
        }

        // 清理
        $adapter->delete($path);
    }

    public function testUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/file.jpg';

        $url = $adapter->url($path);
        $this->assertIsString($url);
        $this->assertStringContainsString($path, $url);
    }

    public function testCdnUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/file.jpg';

        $cdnUrl = $adapter->cdnUrl($path);
        if ($adapter->getCdnBaseUrl()) {
            $this->assertIsString($cdnUrl);
            $this->assertStringContainsString($path, $cdnUrl);
        } else {
            $this->assertNull($cdnUrl);
        }
    }

    public function testAuthUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/file.jpg';

        $authUrl = $adapter->authUrl($path, 60);
        $this->assertIsString($authUrl);
        $this->assertStringContainsString('Signature', $authUrl);
    }

    #[DataProvider('isBucketUrlProvider')]
    public function testIsBucketUrl(string $url, bool $expected, array $config = []): void
    {
        $adapter = $this->createMockAdapter($config);
        $this->assertSame($expected, $adapter->isBucketUrl($url));
    }

    public static function isBucketUrlProvider(): array
    {
        return [
            'cdn domain' => ['https://cdn.example.com/file.jpg', true, ['cdnBaseUrl' => 'https://cdn.example.com']],
            'upload domain' => ['https://upload.example.com/file.jpg', true, ['uploadBaseUrl' => 'https://upload.example.com']],
            'oss original' => ['https://test-bucket.oss-cn-hangzhou.aliyuncs.com/file.jpg', true, ['bucket' => 'test-bucket', 'regionId' => 'cn-hangzhou']],
            'oss internal' => ['https://test-bucket.oss-cn-hangzhou-internal.aliyuncs.com/file.jpg', true, ['bucket' => 'test-bucket', 'regionId' => 'cn-hangzhou']],
            'other domain' => ['https://other.example.com/file.jpg', false, []],
            'invalid url' => ['not-a-url', false, []],
            'empty string' => ['', false, []],
        ];
    }

    public function testHasUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';

        $adapter->write($path, 'content', new Config());

        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);
        $this->assertTrue($adapter->hasUrl($url));

        // 清理
        $adapter->delete($path);

        // 删除后应该返回 false
        $this->assertFalse($adapter->hasUrl($url));
    }

    #[DataProvider('isValidUrlProvider')]
    public function testIsValidUrl(mixed $url, bool $expectedDomainOnly): void
    {
        $adapter = $this->createMockAdapter([
            'cdnBaseUrl' => 'https://cdn.example.com',
            'uploadBaseUrl' => 'https://upload.example.com',
            'bucket' => 'test-bucket',
            'regionId' => 'cn-hangzhou',
        ]);

        $this->assertSame($expectedDomainOnly, $adapter->isValidUrl($url, true, false));
    }

    public static function isValidUrlProvider(): array
    {
        return [
            'valid cdn url' => ['https://cdn.example.com/file.jpg', true],
            'valid upload url' => ['https://upload.example.com/file.jpg', true],
            'valid oss url' => ['https://test-bucket.oss-cn-hangzhou.aliyuncs.com/file.jpg', true],
            'invalid domain' => ['https://other.example.com/file.jpg', false],
            'empty string' => ['', false],
            'null value' => [null, false],
            'integer value' => [123, false],
            'not a url' => ['not-a-url', false],
        ];
    }

    public function testIsValidUrlWithBothChecksDisabled(): void
    {
        $adapter = $this->createMockAdapter();

        $this->assertTrue($adapter->isValidUrl('https://any.domain.com/file.jpg', false, false));
        $this->assertFalse($adapter->isValidUrl('not-a-url', false, false));
        $this->assertFalse($adapter->isValidUrl('', false, false));
    }

    public function testForbidOverwriteOptions(): void
    {
        $adapter = $this->getOssAdapter();
        $options = $adapter->forbidOverwriteOptions();

        $this->assertInstanceOf(Config::class, $options);
    }

    public function testGetPrefixer(): void
    {
        $adapter = $this->createMockAdapter(['prefix' => 'my-prefix']);
        $prefixer = $adapter->getPrefixer();

        // 验证前缀路径使用 '/' 而不是 DIRECTORY_SEPARATOR
        $this->assertSame('my-prefix/test/file.txt', $prefixer->prefixPath('test/file.txt'));
    }

    public function testGetPrefixerWithEmptyPrefix(): void
    {
        $adapter = $this->createMockAdapter(['prefix' => '']);
        $prefixer = $adapter->getPrefixer();

        $this->assertSame('test/file.txt', $prefixer->prefixPath('test/file.txt'));
    }

    public function testGetPrefixerWithNullPrefix(): void
    {
        $adapter = $this->createMockAdapter(['prefix' => null]);
        $prefixer = $adapter->getPrefixer();

        $this->assertSame('test/file.txt', $prefixer->prefixPath('test/file.txt'));
    }

    public function testMakePath(): void
    {
        $adapter = $this->createMockAdapter(['prefix' => 'prefix']);

        // 默认带前缀
        $this->assertSame('prefix/file.txt', $adapter->makePath('file.txt'));

        // 显式带前缀
        $this->assertSame('prefix/file.txt', $adapter->makePath('file.txt', new Config(['with_prefix' => true])));

        // 不带前缀
        $this->assertSame('file.txt', $adapter->makePath('file.txt', new Config(['with_prefix' => false])));
    }

    public function testGetFileAttributes(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';
        $content = Str::random(50);

        $adapter->write($path, $content, new Config());

        $attrs = $adapter->getFileAttributes($path);
        $this->assertSame($path, $attrs->path());
        $this->assertSame(strlen($content), $attrs->fileSize());
        $this->assertNotNull($attrs->lastModified());
        $this->assertNotNull($attrs->mimeType());

        // 清理
        $adapter->delete($path);
    }

    public function testDirectoryExists(): void
    {
        $adapter = $this->getOssAdapter();

        // OSS 中目录总是存在
        $this->assertTrue($adapter->directoryExists('any/path'));
    }

    public function testCreateDirectory(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test-dir-' . Str::random(16);

        // 创建目录不应抛出异常
        $adapter->createDirectory($path);

        // 验证目录对象存在（OSS 目录是以 / 结尾的空对象）
        $this->assertTrue($adapter->fileExists($path . '/'));

        // 清理
        $adapter->delete($path . '/');
    }

    public function testDeleteDirectoryThrowsException(): void
    {
        $adapter = $this->getOssAdapter();

        $this->expectException(\BadMethodCallException::class);
        $adapter->deleteDirectory('any/path');
    }

    public function testListContentsThrowsException(): void
    {
        $adapter = $this->getOssAdapter();

        $this->expectException(\BadMethodCallException::class);
        $adapter->listContents('any/path', false);
    }

    public function testIsValidUrlWithFileExistsCheck(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';

        $adapter->write($path, 'content', new Config());
        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        // 文件存在，验证通过
        $this->assertTrue($adapter->isValidUrl($url, false, true));

        // 清理
        $adapter->delete($path);

        // 文件不存在，验证失败
        $this->assertFalse($adapter->isValidUrl($url, false, true));
    }

    public function testIsValidUrlWithAllChecks(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';

        $adapter->write($path, 'content', new Config());
        $url = $adapter->cdnUrl($path) ?: $adapter->url($path);

        // 域名和文件都检查
        $this->assertTrue($adapter->isValidUrl($url, true, true));

        // 清理
        $adapter->delete($path);
    }

    public function testAuthUploadUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/upload-file.txt';

        $authUrl = $adapter->authUploadUrl($path, 60);
        $this->assertIsString($authUrl);
        $this->assertStringContainsString('Signature', $authUrl);
    }

    public function testAuthUploadUrlWithFullUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $url = ($adapter->getUploadBaseUrl() ?: 'https://example.com') . '/test/file.txt';

        $authUrl = $adapter->authUploadUrl($url, 60);
        $this->assertIsString($authUrl);
        $this->assertStringContainsString('Signature', $authUrl);
    }

    public function testPutFileAndPutFileAndReturnUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';

        // 创建临时文件
        $tempFile = sys_get_temp_dir() . '/' . Str::random(16) . '.txt';
        file_put_contents($tempFile, 'test content');

        try {
            $adapter->putFile($tempFile, $path);
            $this->assertTrue($adapter->fileExists($path));
            $this->assertSame('test content', $adapter->read($path));

            // 清理 OSS 文件
            $adapter->delete($path);
        } finally {
            // 清理临时文件
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testPutFileAndReturnUrl(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';

        // 创建临时文件
        $tempFile = sys_get_temp_dir() . '/' . Str::random(16) . '.txt';
        file_put_contents($tempFile, 'test content');

        try {
            $url = $adapter->putFileAndReturnUrl($tempFile, $path);
            $this->assertIsString($url);
            $this->assertStringContainsString($path, $url);

            // 清理 OSS 文件
            $adapter->delete($path);
        } finally {
            // 清理临时文件
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testDownload(): void
    {
        $adapter = $this->getOssAdapter();
        $path = 'test/' . Str::random(32) . '.txt';
        $content = Str::random(100);

        $adapter->write($path, $content, new Config());

        // 下载到临时文件
        $tempFile = sys_get_temp_dir() . '/' . Str::random(16) . '.txt';

        try {
            $adapter->download($path, $tempFile);
            $this->assertFileExists($tempFile);
            $this->assertSame($content, file_get_contents($tempFile));
        } finally {
            // 清理
            $adapter->delete($path);
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testBase64EncodeWatermarkText(): void
    {
        // 测试标准文本
        $text = '水印文字';
        $encoded = OssAdapter::base64EncodeWatermarkText($text);

        // 验证是 URL 安全的 base64
        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);

        // 验证可以解码回原文
        $decoded = base64_decode(strtr($encoded, ['-' => '+', '_' => '/']));
        $this->assertSame($text, $decoded);
    }

    public function testMagicCallMethod(): void
    {
        $adapter = $this->getOssAdapter();

        // 调用 OssClient 的方法
        $bucket = $adapter->getBucket();
        $this->assertNotEmpty($bucket);
    }

    public function testGetAccessKeyId(): void
    {
        $adapter = $this->createMockAdapter(['accessKeyId' => 'test-key-id']);
        $this->assertSame('test-key-id', $adapter->getAccessKeyId());
    }

    public function testGetAccessKeySecret(): void
    {
        $adapter = $this->createMockAdapter(['accessKeySecret' => 'test-key-secret']);
        $this->assertSame('test-key-secret', $adapter->getAccessKeySecret());
    }

    public function testCdnUrlWithFullUrl(): void
    {
        $adapter = $this->createMockAdapter(['cdnBaseUrl' => 'https://cdn.example.com']);

        // 传入完整 URL，应该替换 host
        $url = $adapter->cdnUrl('https://other.example.com/path/to/file.jpg');
        $this->assertSame('https://cdn.example.com/path/to/file.jpg', $url);
    }

    public function testCdnUrlWithoutCdnBaseUrl(): void
    {
        $adapter = $this->createMockAdapter(['cdnBaseUrl' => null]);

        $url = $adapter->cdnUrl('test/file.jpg');
        $this->assertNull($url);
    }

    public function testAuthUrlWithFullUrl(): void
    {
        $adapter = $this->getOssAdapter();

        // 传入完整 URL
        $baseUrl = $adapter->getCdnBaseUrl() ?: $adapter->getUploadBaseUrl() ?: 'https://example.com';
        $fullUrl = $baseUrl . '/test/file.jpg';

        $authUrl = $adapter->authUrl($fullUrl, 60);
        $this->assertIsString($authUrl);
        $this->assertStringContainsString('Signature', $authUrl);
    }
}
