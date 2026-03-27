<?php

namespace HughCube\Laravel\AliOSS\Tests;

use HughCube\Laravel\AliOSS\OssAdapter;
use HughCube\Laravel\AliOSS\OssUrl;

class OssUrlTest extends TestCase
{
    private function makeUrl(string $url = 'https://cdn.example.com/path/file.jpg'): OssUrl
    {
        $adapter = $this->createMockAdapter([
            'bucket' => 'test-bucket',
            'region' => 'cn-hangzhou',
            'cdnBaseUrl' => 'https://cdn.example.com',
            'uploadBaseUrl' => 'https://upload.example.com',
        ]);

        return OssUrl::from($adapter, $url);
    }

    // ==================== 工厂方法 ====================

    public function testFrom(): void
    {
        $url = $this->makeUrl();
        $this->assertInstanceOf(OssUrl::class, $url);
        $this->assertSame('https://cdn.example.com/path/file.jpg', (string) $url);
    }

    public function testTryFromWithValidUrl(): void
    {
        $adapter = $this->createMockAdapter();
        $url = OssUrl::tryFrom($adapter, 'https://example.com/file.jpg');
        $this->assertInstanceOf(OssUrl::class, $url);
    }

    public function testTryFromWithInvalidUrl(): void
    {
        $adapter = $this->createMockAdapter();
        $this->assertNull(OssUrl::tryFrom($adapter, 'not-a-url'));
    }

    // ==================== 域名转换 ====================

    public function testToCdn(): void
    {
        $url = $this->makeUrl('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/path/file.jpg');
        $cdn = $url->toCdn();
        $this->assertSame('https://cdn.example.com/path/file.jpg', (string) $cdn);
    }

    public function testToCdnReturnsNullWhenNotConfigured(): void
    {
        $adapter = $this->createMockAdapter(['cdnBaseUrl' => null]);
        $url = OssUrl::from($adapter, 'https://example.com/file.jpg');
        $this->assertNull($url->toCdn());
    }

    public function testToUpload(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/path/file.jpg');
        $upload = $url->toUpload();
        $this->assertSame('https://upload.example.com/path/file.jpg', (string) $upload);
    }

    public function testToOss(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/path/file.jpg');
        $oss = $url->toOss();
        $this->assertSame('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/path/file.jpg', (string) $oss);
    }

    public function testToOssInternal(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/path/file.jpg');
        $internal = $url->toOssInternal();
        $this->assertSame('https://test-bucket.oss-cn-hangzhou-internal.aliyuncs.com/path/file.jpg', (string) $internal);
    }

    public function testToOssUri(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/path/file.jpg');
        $this->assertSame('oss://test-bucket/path/file.jpg', $url->toOssUri());
    }

    // ==================== 域名识别 ====================

    public function testIsCdn(): void
    {
        $this->assertTrue($this->makeUrl('https://cdn.example.com/file.jpg')->isCdn());
        $this->assertFalse($this->makeUrl('https://other.com/file.jpg')->isCdn());
    }

    public function testIsUpload(): void
    {
        $this->assertTrue($this->makeUrl('https://upload.example.com/file.jpg')->isUpload());
        $this->assertFalse($this->makeUrl('https://other.com/file.jpg')->isUpload());
    }

    public function testIsOss(): void
    {
        $this->assertTrue($this->makeUrl('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/file.jpg')->isOss());
        $this->assertFalse($this->makeUrl('https://other.com/file.jpg')->isOss());
    }

    public function testIsOssInternal(): void
    {
        $this->assertTrue($this->makeUrl('https://test-bucket.oss-cn-hangzhou-internal.aliyuncs.com/file.jpg')->isOssInternal());
    }

    public function testIsBucket(): void
    {
        $this->assertTrue($this->makeUrl('https://cdn.example.com/file.jpg')->isBucket());
        $this->assertTrue($this->makeUrl('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/file.jpg')->isBucket());
        $this->assertFalse($this->makeUrl('https://other.com/file.jpg')->isBucket());
    }

    // ==================== 签名 ====================

    public function testSign(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl('test/file.jpg');
        $signed = $url->sign(60);
        $this->assertStringContainsString('x-oss-signature', (string) $signed);
    }

    public function testSignUpload(): void
    {
        $adapter = $this->getOssAdapter();
        $url = $adapter->ossUrl('test/file.jpg');
        $signed = $url->signUpload(60);
        $this->assertStringContainsString('x-oss-signature', (string) $signed);
    }

    // ==================== 通用 process ====================

    public function testProcess(): void
    {
        $url = $this->makeUrl()->process('image/resize,w_800');
        $this->assertStringContainsString('x-oss-process=image/resize,w_800', (string) $url);
    }

    public function testAsyncProcess(): void
    {
        $url = $this->makeUrl()->asyncProcess('video/convert,f_mp4');
        $this->assertStringContainsString('x-oss-async-process=video/convert,f_mp4', (string) $url);
    }

    public function testClearProcess(): void
    {
        $url = $this->makeUrl()
            ->process('image/resize,w_800')
            ->asyncProcess('video/convert,f_mp4')
            ->clearProcess();
        $str = (string) $url;
        $this->assertStringNotContainsString('x-oss-process', $str);
        $this->assertStringNotContainsString('x-oss-async-process', $str);
    }

    // ==================== 图片处理 ====================

    public function testImageResize(): void
    {
        $url = $this->makeUrl()->imageResize(800, 600, 'fill');
        $this->assertStringContainsString('x-oss-process=image/resize,m_fill,w_800,h_600', (string) $url);
    }

    public function testImageResizeWidthOnly(): void
    {
        $url = $this->makeUrl()->imageResize(800);
        $this->assertStringContainsString('resize,m_lfit,w_800', (string) $url);
        $this->assertStringNotContainsString(',h_', (string) $url);
    }

    public function testImageResizeByPercent(): void
    {
        $url = $this->makeUrl()->imageResizeByPercent(50);
        $this->assertStringContainsString('resize,p_50', (string) $url);
    }

    public function testImageCrop(): void
    {
        $url = $this->makeUrl()->imageCrop(500, 500, 'se', 10, 20);
        $this->assertStringContainsString('crop,w_500,h_500,g_se,x_10,y_20', (string) $url);
    }

    public function testImageCropDefaultGravity(): void
    {
        $url = $this->makeUrl()->imageCrop(500, 500);
        $this->assertStringContainsString('crop,w_500,h_500,g_center', (string) $url);
    }

    public function testImageRotate(): void
    {
        $url = $this->makeUrl()->imageRotate(90);
        $this->assertStringContainsString('rotate,90', (string) $url);
    }

    public function testImageFlipHorizontal(): void
    {
        $url = $this->makeUrl()->imageFlip('h');
        $this->assertStringContainsString('flip,1', (string) $url);
    }

    public function testImageFlipVertical(): void
    {
        $url = $this->makeUrl()->imageFlip('v');
        $this->assertStringContainsString('flip,0', (string) $url);
    }

    public function testImageFlipBoth(): void
    {
        $url = $this->makeUrl()->imageFlip('both');
        $this->assertStringContainsString('flip,2', (string) $url);
    }

    public function testImageFormat(): void
    {
        $url = $this->makeUrl()->imageFormat('webp');
        $this->assertStringContainsString('format,webp', (string) $url);
    }

    public function testImageQualityRelative(): void
    {
        $url = $this->makeUrl()->imageQuality(80);
        $this->assertStringContainsString('quality,q_80', (string) $url);
    }

    public function testImageQualityAbsolute(): void
    {
        $url = $this->makeUrl()->imageQuality(90, true);
        $this->assertStringContainsString('quality,Q_90', (string) $url);
    }

    public function testImageBlur(): void
    {
        $url = $this->makeUrl()->imageBlur(10, 10);
        $this->assertStringContainsString('blur,r_10,s_10', (string) $url);
    }

    public function testImageBright(): void
    {
        $url = $this->makeUrl()->imageBright(50);
        $this->assertStringContainsString('bright,50', (string) $url);
    }

    public function testImageContrast(): void
    {
        $url = $this->makeUrl()->imageContrast(-50);
        $this->assertStringContainsString('contrast,-50', (string) $url);
    }

    public function testImageSharpen(): void
    {
        $url = $this->makeUrl()->imageSharpen(100);
        $this->assertStringContainsString('sharpen,100', (string) $url);
    }

    public function testImageCircle(): void
    {
        $url = $this->makeUrl()->imageCircle(100);
        $this->assertStringContainsString('circle,r_100', (string) $url);
    }

    public function testImageRoundedCorners(): void
    {
        $url = $this->makeUrl()->imageRoundedCorners(30);
        $this->assertStringContainsString('rounded-corners,r_30', (string) $url);
    }

    public function testImageAutoOrient(): void
    {
        $url = $this->makeUrl()->imageAutoOrient();
        $this->assertStringContainsString('auto-orient,1', (string) $url);
    }

    public function testImageInterlace(): void
    {
        $url = $this->makeUrl()->imageInterlace();
        $this->assertStringContainsString('interlace,1', (string) $url);
    }

    public function testImageIndexCrop(): void
    {
        $url = $this->makeUrl()->imageIndexCrop(100, 0, 'x');
        $this->assertStringContainsString('indexcrop,x_100,i_0', (string) $url);
    }

    public function testImageWatermarkText(): void
    {
        $url = $this->makeUrl()->imageWatermarkText('Hello', 30, 'FF0000', 'se', 10, 10, 80);
        $str = (string) $url;
        $this->assertStringContainsString('watermark,text_', $str);
        $this->assertStringContainsString('size_30', $str);
        $this->assertStringContainsString('color_FF0000', $str);
        $this->assertStringContainsString('g_se', $str);
    }

    public function testImageWatermarkTextWithOptionalParams(): void
    {
        $url = $this->makeUrl()->imageWatermarkText('Hello', font: 'Arial', shadow: 50, rotate: 45, fill: true);
        $str = (string) $url;
        $this->assertStringContainsString('shadow_50', $str);
        $this->assertStringContainsString('rotate_45', $str);
        $this->assertStringContainsString('fill_1', $str);
    }

    public function testImageWatermarkImage(): void
    {
        $url = $this->makeUrl()->imageWatermarkImage('logo.png', 'nw', 5, 5, 90, 30);
        $str = (string) $url;
        $this->assertStringContainsString('watermark,image_', $str);
        $this->assertStringContainsString('g_nw', $str);
        $this->assertStringContainsString('P_30', $str);
    }

    public function testImageInfo(): void
    {
        $url = $this->makeUrl()->imageInfo();
        $this->assertStringContainsString('x-oss-process=image/info', (string) $url);
    }

    public function testImageAverageHue(): void
    {
        $url = $this->makeUrl()->imageAverageHue();
        $this->assertStringContainsString('x-oss-process=image/average-hue', (string) $url);
    }

    // ==================== 图片链式叠加 ====================

    public function testImageChainedOperations(): void
    {
        $url = $this->makeUrl()
            ->imageResize(800)
            ->imageRotate(90)
            ->imageWatermarkText('水印')
            ->imageFormat('webp')
            ->imageQuality(85);

        $str = (string) $url;
        // image/ 前缀只出现一次
        $this->assertSame(1, substr_count($str, 'image/'));
        // 所有操作都在
        $this->assertStringContainsString('resize,', $str);
        $this->assertStringContainsString('rotate,90', $str);
        $this->assertStringContainsString('watermark,text_', $str);
        $this->assertStringContainsString('format,webp', $str);
        $this->assertStringContainsString('quality,q_85', $str);
    }

    // ==================== 图片移除 ====================

    public function testImageRemoveResize(): void
    {
        $url = $this->makeUrl()
            ->imageResize(800)
            ->imageRotate(90)
            ->imageRemoveResize();

        $str = (string) $url;
        $this->assertStringNotContainsString('resize', $str);
        $this->assertStringContainsString('rotate,90', $str);
    }

    public function testImageRemoveWatermark(): void
    {
        $url = $this->makeUrl()
            ->imageResize(800)
            ->imageWatermarkText('文字')
            ->imageRemoveWatermark();

        $str = (string) $url;
        $this->assertStringNotContainsString('watermark', $str);
        $this->assertStringContainsString('resize', $str);
    }

    public function testImageRemoveAllOperationsClears(): void
    {
        $url = $this->makeUrl()
            ->imageResize(800)
            ->imageRemoveResize();

        $str = (string) $url;
        $this->assertStringNotContainsString('x-oss-process', $str);
    }

    // ==================== 视频处理 ====================

    public function testVideoSnapshot(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/video.mp4')
            ->videoSnapshot(1000, 800, 600, 'jpg', 'fast');
        $str = (string) $url;
        $this->assertStringContainsString('video/snapshot', $str);
        $this->assertStringContainsString('t_1000', $str);
        $this->assertStringContainsString('w_800', $str);
        $this->assertStringContainsString('h_600', $str);
        $this->assertStringContainsString('f_jpg', $str);
        $this->assertStringContainsString('m_fast', $str);
    }

    public function testVideoSnapshotMinimalParams(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/video.mp4')
            ->videoSnapshot(0);
        $str = (string) $url;
        $this->assertStringContainsString('t_0', $str);
        $this->assertStringNotContainsString('w_', $str);
        $this->assertStringNotContainsString('h_', $str);
    }

    public function testVideoInfo(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/video.mp4')->videoInfo();
        $this->assertStringContainsString('x-oss-process=video/info', (string) $url);
    }

    public function testVideoConvert(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/video.mp4')
            ->videoConvert('mp4', 'h264', 'aac', '1280x720', 2000, 128, 30.0);
        $str = (string) $url;
        $this->assertStringContainsString('x-oss-async-process=video/convert', $str);
        $this->assertStringContainsString('f_mp4', $str);
        $this->assertStringContainsString('vcodec_h264', $str);
        $this->assertStringContainsString('acodec_aac', $str);
        $this->assertStringContainsString('s_1280x720', $str);
    }

    public function testVideoGif(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/video.mp4')
            ->videoGif(5000, 3000, 320, 240, 10.0);
        $str = (string) $url;
        $this->assertStringContainsString('x-oss-async-process=video/gif', $str);
        $this->assertStringContainsString('ss_5000', $str);
        $this->assertStringContainsString('t_3000', $str);
    }

    public function testVideoSprite(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/video.mp4')
            ->videoSprite(5, 10, 10, 160, 90);
        $str = (string) $url;
        $this->assertStringContainsString('x-oss-async-process=video/sprite', $str);
        $this->assertStringContainsString('interval_5', $str);
        $this->assertStringContainsString('columns_10', $str);
    }

    public function testVideoConcat(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/video1.mp4')
            ->videoConcat(['video2.mp4', 'video3.mp4']);
        $str = (string) $url;
        $this->assertStringContainsString('x-oss-async-process=video/concat', $str);
        $this->assertStringContainsString('source_', $str);
    }

    public function testVideoRemoveSnapshot(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/video.mp4')
            ->videoSnapshot(1000)
            ->videoRemoveSnapshot();
        $this->assertStringNotContainsString('snapshot', (string) $url);
    }

    // ==================== 音频处理 ====================

    public function testAudioInfo(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/audio.mp3')->audioInfo();
        $this->assertStringContainsString('x-oss-process=audio/info', (string) $url);
    }

    public function testAudioConvert(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/audio.wav')
            ->audioConvert('mp3', 44100, 2, 320);
        $str = (string) $url;
        $this->assertStringContainsString('x-oss-async-process=audio/convert', $str);
        $this->assertStringContainsString('f_mp3', $str);
        $this->assertStringContainsString('ar_44100', $str);
        $this->assertStringContainsString('ac_2', $str);
        $this->assertStringContainsString('ab_320', $str);
    }

    public function testAudioConcat(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/audio1.mp3')
            ->audioConcat(['audio2.mp3', 'audio3.mp3']);
        $str = (string) $url;
        $this->assertStringContainsString('x-oss-async-process=audio/concat', $str);
    }

    public function testAudioRemoveConvert(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/audio.wav')
            ->audioConvert('mp3')
            ->audioRemoveConvert();
        $this->assertStringNotContainsString('convert', (string) $url);
    }

    // ==================== 文档处理 ====================

    public function testDocPreview(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/doc.docx')->docPreview();
        $this->assertStringContainsString('x-oss-process=doc/preview', (string) $url);
    }

    public function testDocEdit(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/doc.docx')->docEdit();
        $this->assertStringContainsString('x-oss-process=doc/edit', (string) $url);
    }

    public function testDocSnapshot(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/doc.docx')->docSnapshot(3);
        $this->assertStringContainsString('doc/snapshot,page_3', (string) $url);
    }

    public function testDocSnapshotWithoutPage(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/doc.docx')->docSnapshot();
        $this->assertStringContainsString('doc/snapshot', (string) $url);
        $this->assertStringNotContainsString('page_', (string) $url);
    }

    public function testDocConvert(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/doc.docx')
            ->docConvert('pdf', 'docx', '1,2,4-10');
        $str = (string) $url;
        $this->assertStringContainsString('x-oss-async-process=doc/convert', $str);
        $this->assertStringContainsString('target_pdf', $str);
        $this->assertStringContainsString('source_docx', $str);
        $this->assertStringContainsString('pages_1,2,4-10', $str);
    }

    public function testDocTranslate(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/doc.docx')
            ->docTranslate('Hello World', 'zh_CN');
        $str = (string) $url;
        $this->assertStringContainsString('x-oss-process=doc/translate', $str);
        $this->assertStringContainsString('language_zh_CN', $str);
        $this->assertStringContainsString('content_', $str);
    }

    public function testDocRemovePreview(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/doc.docx')
            ->docPreview()
            ->docRemovePreview();
        $this->assertStringNotContainsString('preview', (string) $url);
    }

    // ==================== 异步辅助 ====================

    public function testSaveas(): void
    {
        $url = $this->makeUrl()
            ->asyncProcess('video/convert,f_mp4')
            ->saveas('my-bucket', 'output/result.mp4');
        $str = (string) $url;
        $this->assertStringContainsString('sys/saveas,o_', $str);
    }

    public function testNotify(): void
    {
        $url = $this->makeUrl()
            ->asyncProcess('video/convert,f_mp4')
            ->notify('my-topic');
        $str = (string) $url;
        $this->assertStringContainsString('sys/notify,topic_', $str);
    }

    // ==================== 组合场景：同步 + 异步共存 ====================

    public function testSyncAndAsyncCoexist(): void
    {
        $url = $this->makeUrl()
            ->imageResize(800)
            ->imageFormat('webp')
            ->asyncProcess('video/convert,f_mp4');

        $str = (string) $url;
        // 同步和异步参数同时存在
        $this->assertStringContainsString('x-oss-process=image/resize', $str);
        $this->assertStringContainsString('x-oss-async-process=video/convert', $str);
    }

    // ==================== 组合场景：图片 + 文档共存 ====================

    public function testImageAndDocCoexist(): void
    {
        $url = $this->makeUrl()
            ->imageResize(800)
            ->docPreview();

        $str = (string) $url;
        // 不同前缀各自独立
        $this->assertStringContainsString('image/resize', $str);
        $this->assertStringContainsString('doc/preview', $str);
    }

    // ==================== 组合场景：多种类型混合 ====================

    public function testMixedTypesInProcess(): void
    {
        $url = $this->makeUrl()
            ->imageResize(800)
            ->imageWatermarkText('水印')
            ->process('video/snapshot,t_1000');

        $str = (string) $url;
        $this->assertStringContainsString('image/resize', $str);
        $this->assertStringContainsString('watermark,text_', $str);
        $this->assertStringContainsString('video/snapshot,t_1000', $str);
    }

    // ==================== 组合场景：异步 + saveas + notify ====================

    public function testAsyncWithSaveasAndNotify(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/video.mp4')
            ->videoConvert('mp4', 'h264')
            ->saveas('output-bucket', 'result.mp4')
            ->notify('my-topic');

        $str = (string) $url;
        $this->assertStringContainsString('x-oss-async-process=video/convert', $str);
        // saveas 和 notify 是 sys/ 前缀，同前缀合并后 notify 省略 sys/ 前缀
        $this->assertStringContainsString('sys/saveas', $str);
        $this->assertStringContainsString('notify,topic_', $str);
    }

    // ==================== 域名转换保留 process 参数 ====================

    public function testDomainConversionPreservesProcess(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/photo.jpg')
            ->imageResize(800)
            ->imageFormat('webp');

        $oss = $url->toOss();
        $str = (string) $oss;
        // 域名变了
        $this->assertStringContainsString('test-bucket.oss-cn-hangzhou.aliyuncs.com', $str);
        // process 参数保留
        $this->assertStringContainsString('x-oss-process=image/resize', $str);
        $this->assertStringContainsString('format,webp', $str);
    }

    // ==================== 不可变性测试 ====================

    public function testImmutability(): void
    {
        $original = $this->makeUrl();
        $modified = $original->imageResize(800);

        // 原始对象不受影响
        $this->assertStringNotContainsString('x-oss-process', (string) $original);
        $this->assertStringContainsString('x-oss-process', (string) $modified);
    }

    // ==================== 边界情况 ====================

    public function testEmptyProcessString(): void
    {
        $url = $this->makeUrl();
        $this->assertSame('https://cdn.example.com/path/file.jpg', (string) $url);
    }

    public function testProcessOnUrlWithExistingQuery(): void
    {
        $url = $this->makeUrl('https://cdn.example.com/file.jpg?token=abc');
        $processed = $url->imageResize(800);
        $str = (string) $processed;
        $this->assertStringContainsString('token=abc', $str);
        $this->assertStringContainsString('x-oss-process=image/resize', $str);
    }
}
