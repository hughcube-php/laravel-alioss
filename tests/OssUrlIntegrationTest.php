<?php

namespace HughCube\Laravel\AliOSS\Tests;

use GuzzleHttp\Client as HttpClient;
use HughCube\Laravel\AliOSS\OssUrl;
use Illuminate\Support\Str;

/**
 * OssUrl 真实 OSS 集成测试
 *
 * 这些测试会上传文件到真实 OSS 并验证各种处理 URL 是否能正确返回。
 */
class OssUrlIntegrationTest extends TestCase
{
    private static ?string $imagePath = null;
    private static ?string $textPath = null;
    private static bool $initialized = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$initialized) {
            $adapter = $this->getOssAdapter();

            // 上传一张真实 PNG 图片（1x1 像素红色）
            self::$imagePath = 'test/oss-url-test-' . Str::random(16) . '.png';
            $png = $this->createTestPng();
            $adapter->write(self::$imagePath, $png);

            // 上传一个文本文件
            self::$textPath = 'test/oss-url-test-' . Str::random(16) . '.txt';
            $adapter->write(self::$textPath, 'Hello OSS URL Integration Test');

            self::$initialized = true;
        }
    }

    public static function tearDownAfterClass(): void
    {
        // 注意：需要在子类中手动清理，因为 static 方法没有 $this
        // 清理在最后一个测试的 tearDown 中处理
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * 最后清理测试文件
     */
    public function testZzCleanup(): void
    {
        $adapter = $this->getOssAdapter();

        if (self::$imagePath !== null) {
            $adapter->delete(self::$imagePath);
            $this->assertFalse($adapter->fileExists(self::$imagePath));
        }

        if (self::$textPath !== null) {
            $adapter->delete(self::$textPath);
            $this->assertFalse($adapter->fileExists(self::$textPath));
        }

        self::$initialized = false;
        $this->assertTrue(true);
    }

    // ==================== 基础 URL 生成 ====================

    public function testOssUrlReturnsOssUrlInstance(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath);

        $this->assertInstanceOf(OssUrl::class, $url);
        $this->assertStringContainsString(self::$imagePath, (string) $url);
    }

    public function testCdnUrlReturnsOssUrlInstance(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->cdnUrl(self::$imagePath);

        if ($url !== null) {
            $this->assertInstanceOf(OssUrl::class, $url);
        } else {
            $this->markTestSkipped('CDN not configured');
        }
    }

    // ==================== 签名 URL 可访问 ====================

    public function testSignedUrlIsAccessible(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$textPath)->sign(300);

        $response = (new HttpClient())->get((string) $url);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello OSS URL Integration Test', $response->getBody()->getContents());
    }

    // ==================== 图片处理：单个操作 ====================

    public function testImageResizeIsAccessible(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath)
            ->imageResize(100, 100)
            ->sign(300);

        $response = (new HttpClient())->get((string) $url);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('image/', $response->getHeaderLine('Content-Type'));
    }

    public function testImageFormatConversion(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath)
            ->imageFormat('jpg')
            ->sign(300);

        $response = (new HttpClient())->get((string) $url);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('jpeg', $response->getHeaderLine('Content-Type'));
    }

    public function testImageRotate(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath)
            ->imageRotate(90)
            ->sign(300);

        $response = (new HttpClient())->get((string) $url);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testImageBlur(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath)
            ->imageBlur(10, 10)
            ->sign(300);

        $response = (new HttpClient())->get((string) $url);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testImageQuality(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath)
            ->imageFormat('jpg')
            ->imageQuality(50)
            ->sign(300);

        $response = (new HttpClient())->get((string) $url);
        $this->assertSame(200, $response->getStatusCode());
    }

    // ==================== 图片处理：叠加组合 ====================

    public function testImageChainedResizeRotateFormat(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath)
            ->imageResize(200, 200, 'fill')
            ->imageRotate(45)
            ->imageFormat('jpg')
            ->imageQuality(80)
            ->sign(300);

        $str = (string) $url;
        // 验证 URL 格式正确：image/ 前缀只出现一次
        $processParam = $this->extractProcessParam($str);
        $this->assertSame(1, substr_count($processParam, 'image/'));
        $this->assertStringContainsString('resize,', $processParam);
        $this->assertStringContainsString('rotate,45', $processParam);
        $this->assertStringContainsString('format,jpg', $processParam);
        $this->assertStringContainsString('quality,q_80', $processParam);

        // 验证 OSS 能正确处理
        $response = (new HttpClient())->get($str);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testImageChainedResizeBrightContrastSharpen(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath)
            ->imageResize(100)
            ->imageBright(20)
            ->imageContrast(10)
            ->imageSharpen(100)
            ->sign(300);

        $response = (new HttpClient())->get((string) $url);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testImageChainedResizeRoundedCornersFormatPng(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath)
            ->imageResize(200)
            ->imageRoundedCorners(20)
            ->imageFormat('png')
            ->sign(300);

        $response = (new HttpClient())->get((string) $url);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('png', $response->getHeaderLine('Content-Type'));
    }

    public function testImageChainedResizeCircleFormatPng(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath)
            ->imageCircle(50)
            ->imageFormat('png')
            ->sign(300);

        $response = (new HttpClient())->get((string) $url);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testImageChainedResizeAutoOrient(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath)
            ->imageAutoOrient()
            ->imageResize(100)
            ->sign(300);

        $response = (new HttpClient())->get((string) $url);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testImageChainedResizeWatermark(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath)
            ->imageResize(200)
            ->imageWatermarkText('TEST', 20, 'FF0000', 'center')
            ->imageFormat('jpg')
            ->sign(300);

        $response = (new HttpClient())->get((string) $url);
        $this->assertSame(200, $response->getStatusCode());
    }

    // ==================== imageInfo（独立操作） ====================

    public function testImageInfoReturnsJson(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath)
            ->imageInfo()
            ->sign(300);

        $response = (new HttpClient())->get((string) $url);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('ImageWidth', $data);
        $this->assertArrayHasKey('ImageHeight', $data);
        $this->assertArrayHasKey('Format', $data);
    }

    // ==================== 移除操作后再访问 ====================

    public function testRemoveOperationThenAccess(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath)
            ->imageResize(200)
            ->imageRotate(90)
            ->imageRemoveRotate()
            ->sign(300);

        // URL 中不应包含 rotate
        $processParam = $this->extractProcessParam((string) $url);
        $this->assertStringNotContainsString('rotate', $processParam);
        $this->assertStringContainsString('resize,', $processParam);

        $response = (new HttpClient())->get((string) $url);
        $this->assertSame(200, $response->getStatusCode());
    }

    // ==================== 域名转换后带 process 访问 ====================

    public function testDomainConversionWithProcessThenSign(): void
    {
        $adapter = $this->getOssAdapter();

        // 先构建带 process 的 CDN URL
        $cdnUrl = $adapter->cdnUrl(self::$imagePath);
        if ($cdnUrl === null) {
            $this->markTestSkipped('CDN not configured');
        }

        $processed = $cdnUrl->imageResize(100)->imageFormat('jpg');

        // 转换到 OSS 域名后签名访问
        $ossUrl = $processed->toOss()->sign(300);

        $response = (new HttpClient())->get((string) $ossUrl);
        $this->assertSame(200, $response->getStatusCode());
    }

    // ==================== parseUrl 集成 ====================

    public function testParseUrlThenProcess(): void
    {
        $adapter = $this->getOssAdapter();
        $rawUrl = (string) $adapter->ossUrl(self::$imagePath);

        $parsed = $adapter->parseUrl($rawUrl);
        $this->assertInstanceOf(OssUrl::class, $parsed);

        $processed = $parsed->imageResize(100)->sign(300);
        $response = (new HttpClient())->get((string) $processed);
        $this->assertSame(200, $response->getStatusCode());
    }

    // ==================== ossUri 格式 ====================

    public function testOssUriFormat(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath);
        $uri = $url->toOssUri();

        $this->assertStringStartsWith('oss://', $uri);
        $this->assertStringContainsString($adapter->bucket(), $uri);
        $this->assertStringContainsString(self::$imagePath, $uri);
    }

    // ==================== clearProcess ====================

    public function testClearProcessThenReapply(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl(self::$imagePath)
            ->imageResize(200)
            ->imageRotate(90)
            ->clearProcess()
            ->imageResize(100)
            ->sign(300);

        $processParam = $this->extractProcessParam((string) $url);
        $this->assertStringNotContainsString('rotate', $processParam);
        $this->assertStringContainsString('resize,', $processParam);

        $response = (new HttpClient())->get((string) $url);
        $this->assertSame(200, $response->getStatusCode());
    }

    // ==================== 不可变性验证 ====================

    public function testImmutabilityWithRealOss(): void
    {
        $adapter = $this->getOssAdapter();
        $original = $adapter->ossUrl(self::$imagePath);
        $modified = $original->imageResize(100);

        // original 没有 process 参数
        $this->assertStringNotContainsString('x-oss-process', (string) $original);
        // modified 有
        $this->assertStringContainsString('x-oss-process', (string) $modified);
    }

    // ==================== 辅助方法 ====================

    /**
     * 创建 1x1 像素红色 PNG
     */
    private function createTestPng(): string
    {
        $img = imagecreatetruecolor(100, 100);
        $red = imagecolorallocate($img, 255, 0, 0);
        imagefill($img, 0, 0, $red);

        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);

        return $data;
    }

    /**
     * 从 URL 中提取 x-oss-process 参数值
     */
    private function extractProcessParam(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query === null) {
            return '';
        }

        foreach (explode('&', $query) as $part) {
            if (strpos($part, 'x-oss-process=') === 0) {
                return urldecode(substr($part, strlen('x-oss-process=')));
            }
        }

        return '';
    }
}
