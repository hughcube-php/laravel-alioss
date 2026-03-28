<?php

namespace HughCube\Laravel\AliOSS\Tests;

use GuzzleHttp\Client as HttpClient;
use HughCube\Laravel\AliOSS\OssAdapter;
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

    /** @var array<string> 记录所有创建的文件路径，tearDownAfterClass 统一清理 */
    private static array $createdPaths = [];
    private static ?array $adapterConfig = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$initialized) {
            $adapter = $this->getOssAdapter();

            // 保存配置供 tearDownAfterClass 使用
            self::$adapterConfig = [
                'accessKeyId' => env('ALIOSS_ACCESS_KEY_ID', 'test-access-key-id'),
                'accessKeySecret' => env('ALIOSS_ACCESS_KEY_SECRET', 'test-access-key-secret'),
                'endpoint' => env('ALIOSS_ENDPOINT', 'oss-cn-hangzhou.aliyuncs.com'),
                'bucket' => env('ALIOSS_BUCKET', 'test-bucket'),
                'region' => env('ALIOSS_REGION', 'cn-hangzhou'),
                'isCName' => env('ALIOSS_IS_CNAME', false),
                'prefix' => '',
                'cdnBaseUrl' => env('ALIOSS_CDN_BASE_URL', 'https://cdn.example.com'),
                'uploadBaseUrl' => env('ALIOSS_UPLOAD_BASE_URL', 'https://upload.example.com'),
            ];

            // 上传一张真实 PNG 图片（100x100 像素红色）
            self::$imagePath = $this->testPath('oss-url-test-' . Str::random(16) . '.png');
            $adapter->write(self::$imagePath, $this->createTestPng());
            self::$createdPaths[] = self::$imagePath;

            // 上传一个文本文件
            self::$textPath = $this->testPath('oss-url-test-' . Str::random(16) . '.txt');
            $adapter->write(self::$textPath, 'Hello OSS URL Integration Test');
            self::$createdPaths[] = self::$textPath;

            self::$initialized = true;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$adapterConfig !== null && !empty(self::$createdPaths)) {
            $adapter = new OssAdapter(self::$adapterConfig);
            foreach (self::$createdPaths as $path) {
                try {
                    $adapter->delete($path);
                } catch (\Throwable $e) {
                }
            }
        }

        self::$createdPaths = [];
        self::$imagePath = null;
        self::$textPath = null;
        self::$initialized = false;
        self::$adapterConfig = null;
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
        $processParam = $this->extractProcessParam($str);
        $this->assertSame(1, substr_count($processParam, 'image/'));
        $this->assertStringContainsString('resize,', $processParam);
        $this->assertStringContainsString('rotate,45', $processParam);
        $this->assertStringContainsString('format,jpg', $processParam);
        $this->assertStringContainsString('quality,q_80', $processParam);

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

        $cdnUrl = $adapter->cdnUrl(self::$imagePath);
        if ($cdnUrl === null) {
            $this->markTestSkipped('CDN not configured');
        }

        $processed = $cdnUrl->imageResize(100)->imageFormat('jpg');
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

        $this->assertStringNotContainsString('x-oss-process', (string) $original);
        $this->assertStringContainsString('x-oss-process', (string) $modified);
    }

    // ==================== 辅助方法 ====================

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
