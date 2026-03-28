<?php

namespace HughCube\Laravel\AliOSS;

use Exception;
use HughCube\PUrl\HUrl;

class OssUrl extends HUrl
{
    protected ?OssAdapter $adapter = null;



    // ==================== 工厂方法 ====================

    /**
     * 从 OssAdapter 和 URL 字符串创建 OssUrl 实例。
     *
     * @param OssAdapter $adapter OSS 适配器实例
     * @param string|mixed $url URL 字符串
     * @return static
     */
    public static function from(OssAdapter $adapter, $url): static
    {
        $instance = static::instance($url);
        $instance->adapter = $adapter;

        return $instance;
    }

    /**
     * 安全创建 OssUrl 实例，无效 URL 返回 null。
     *
     * @param OssAdapter $adapter OSS 适配器实例
     * @param string|mixed $url URL 字符串
     * @return static|null 解析成功返回实例，失败返回 null
     */
    public static function tryFrom(OssAdapter $adapter, $url): ?static
    {
        $instance = static::parse($url);
        if ($instance !== null) {
            $instance->adapter = $adapter;
        }

        return $instance;
    }

    // ==================== 域名转换 ====================

    /**
     * 将当前 URL 的域名替换为 CDN 域名。
     *
     * @return static|null CDN 域名未配置时返回 null
     */
    public function toCdn(): ?static
    {
        return $this->convertTo($this->adapter?->cdnDomain(), $this->adapter?->cdnBaseUrl());
    }

    /**
     * 将当前 URL 的域名替换为上传域名。
     *
     * @return static|null 上传域名未配置时返回 null
     */
    public function toUpload(): ?static
    {
        return $this->convertTo($this->adapter?->uploadDomain(), $this->adapter?->uploadBaseUrl());
    }

    /**
     * 将当前 URL 的域名替换为 OSS 外网域名。
     *
     * @return static
     */
    public function toOss(): static
    {
        $domain = $this->adapter?->ossDomain();
        if ($domain === null) {
            return $this;
        }

        /** @var static $new */
        $new = $this->withHost($domain)->withScheme('https');

        return $new;
    }

    /**
     * 将当前 URL 的域名替换为 OSS 内网域名。
     *
     * @return static
     */
    public function toOssInternal(): static
    {
        $domain = $this->adapter?->ossInternalDomain();
        if ($domain === null) {
            return $this;
        }

        /** @var static $new */
        $new = $this->withHost($domain)->withScheme('https');

        return $new;
    }

    /**
     * 将当前 URL 转为 `oss://bucket/key` 格式的 URI。
     *
     * @return string oss:// 格式的 URI
     */
    public function toOssUri(): string
    {
        $bucket = $this->adapter?->bucket() ?? '';
        $path = ltrim($this->getPath(), '/');

        return sprintf('oss://%s/%s', $bucket, $path);
    }

    /**
     * 获取 OSS object key（URL path 去掉前导 /）。
     *
     * @return string OSS key，如 "path/to/file.jpg"
     */
    public function key(): string
    {
        return ltrim($this->getPath(), '/');
    }

    // ==================== 数据操作 ====================

    /**
     * 读取文件内容。
     *
     * @param int|null $timeout 超时秒数，null 使用 SDK 默认配置
     * @return string 文件内容
     */
    public function fetch(?int $timeout = null): string
    {
        if ($this->adapter === null) {
            throw new \BadMethodCallException('OssUrl has no adapter bound.');
        }

        return $this->adapter->fetch('/' . $this->key(), $timeout);
    }

    /**
     * 下载文件到本地。
     *
     * @param string $file 本地保存路径
     * @param int|null $timeout 超时秒数，null 使用 SDK 默认配置
     */
    public function download(string $file, ?int $timeout = null): void
    {
        if ($this->adapter === null) {
            throw new \BadMethodCallException('OssUrl has no adapter bound.');
        }

        $this->adapter->download('/' . $this->key(), $file, $timeout);
    }

    /**
     * 获取文件属性（大小、MIME、最后修改时间）。
     *
     * 文件不存在返回 null 而非抛异常。
     *
     * @param int|null $timeout 超时秒数，null 使用 SDK 默认配置
     * @return \League\Flysystem\FileAttributes|null
     */
    public function fetchAttributes(?int $timeout = null): ?\League\Flysystem\FileAttributes
    {
        return $this->adapter?->fetchAttributes('/' . $this->key(), $timeout);
    }

    /**
     * 获取图片元信息（ImageWidth、ImageHeight、Format、FileSize 等）。
     *
     * 文件不存在或非图片返回 null。
     *
     * @param int|null $timeout 超时秒数，null 使用 SDK 默认配置
     * @return array|null
     */
    public function fetchImageInfo(?int $timeout = null): ?array
    {
        return $this->adapter?->fetchImageInfo('/' . $this->key(), $timeout);
    }

    /**
     * 检测文件是否存在。
     *
     * @param int|null $timeout 超时秒数，null 使用 SDK 默认配置
     * @return bool
     */
    public function exists(?int $timeout = null): bool
    {
        if ($this->adapter === null) {
            return false;
        }

        return $this->adapter->fetchAttributes('/' . $this->key(), $timeout) !== null;
    }

    // ==================== 域名识别 ====================

    /**
     * 检测当前 URL 是否属于 CDN 域名。
     *
     * @return bool
     */
    public function isCdn(): bool
    {
        return $this->adapter !== null && $this->getHost() === $this->adapter->cdnDomain();
    }

    /**
     * 检测当前 URL 是否属于上传域名。
     *
     * @return bool
     */
    public function isUpload(): bool
    {
        return $this->adapter !== null && $this->getHost() === $this->adapter->uploadDomain();
    }

    /**
     * 检测当前 URL 是否属于 OSS 外网域名。
     *
     * @return bool
     */
    public function isOss(): bool
    {
        return $this->adapter !== null && $this->getHost() === $this->adapter->ossDomain();
    }

    /**
     * 检测当前 URL 是否属于 OSS 内网域名。
     *
     * @return bool
     */
    public function isOssInternal(): bool
    {
        return $this->adapter !== null && $this->getHost() === $this->adapter->ossInternalDomain();
    }

    /**
     * 检测当前 URL 是否属于本 Bucket 的任意域名（CDN、上传、OSS 外网、OSS 内网）。
     *
     * @return bool
     */
    public function isBucket(): bool
    {
        return $this->isCdn() || $this->isUpload() || $this->isOss() || $this->isOssInternal();
    }

    // ==================== 签名 ====================

    /**
     * 生成预签名 URL，用于临时授权访问。
     *
     * 当 URL 带有 x-oss-process 参数时（如图片处理），process 会被包含在签名中。
     *
     * @param int $timeout 签名有效期，单位秒，默认 60
     * @param string $method HTTP 方法，枚举值：
     *   - `GET`：下载/访问（默认）
     *   - `PUT`：上传
     *   - `HEAD`：获取元信息
     * @return static 带签名的 OssUrl 实例
     * @throws \Exception
     */
    public function sign(int $timeout = 60, string $method = 'GET'): static
    {
        if ($this->adapter === null) {
            return $this;
        }

        $timeout = max(1, $timeout);

        $key = ltrim($this->getPath(), '/');
        $processValue = $this->extractQueryParam('x-oss-process');

        $request = match (strtoupper($method)) {
            'PUT' => new \AlibabaCloud\Oss\V2\Models\PutObjectRequest(
                bucket: $this->adapter->bucket(),
                key: $key,
            ),
            'HEAD' => new \AlibabaCloud\Oss\V2\Models\HeadObjectRequest(
                bucket: $this->adapter->bucket(),
                key: $key,
            ),
            default => new \AlibabaCloud\Oss\V2\Models\GetObjectRequest(
                bucket: $this->adapter->bucket(),
                key: $key,
                process: !empty($processValue) ? $processValue : null,
            ),
        };

        $presignResult = $this->adapter->client()->presign($request, [
            'expires' => new \DateInterval("PT{$timeout}S"),
        ]);

        $result = static::from($this->adapter, $presignResult->url);

        // 异步参数不参与签名，追加到 URL 后
        $asyncValue = $this->extractQueryParam('x-oss-async-process');
        if (!empty($asyncValue)) {
            $result = $result->setQueryRaw('x-oss-async-process', $asyncValue);
        }

        return $result;
    }

    /**
     * 生成 PUT 预签名 URL 的快捷方法。
     *
     * @param int $timeout 签名有效期，单位秒，默认 60
     * @return static 带签名的 OssUrl 实例
     * @throws Exception
     */
    public function signUpload(int $timeout = 60): static
    {
        return $this->sign($timeout, 'PUT');
    }

    // ==================== 处理参数操作 ====================

    /**
     * 添加同步处理操作段到 x-oss-process 参数。
     *
     * @param string $action 处理操作字符串，如 "image/resize,w_100"
     * @return static
     */
    public function process(string $action): static
    {
        return $this->appendAction('x-oss-process', $action);
    }

    /**
     * 添加异步处理操作段到 x-oss-async-process 参数。
     *
     * @param string $action 处理操作字符串
     * @return static
     */
    public function asyncProcess(string $action): static
    {
        return $this->appendAction('x-oss-async-process', $action);
    }

    /**
     * 清除所有处理参数（同步和异步），移除 x-oss-process 和 x-oss-async-process。
     *
     * @return static
     */
    public function clearProcess(): static
    {
        return $this->removeQueryRaw('x-oss-process')
            ->removeQueryRaw('x-oss-async-process');
    }



    // ==================== 图片处理 image/ ====================

    /**
     * 图片缩放。
     *
     * @param int|null $width 目标宽度，单位像素
     * @param int|null $height 目标高度，单位像素
     * @param string $mode 缩放模式，枚举值：
     *   - `lfit`：等比缩放，限制在指定 w/h 矩形内（默认）
     *   - `mfit`：等比缩放，延伸出指定 w/h 矩形外
     *   - `fill`：先等比缩放 mfit 再居中裁剪
     *   - `pad`：先等比缩放 lfit 再填充
     *   - `fixed`：强制缩放到指定 w/h
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/resize-images-4
     */
    public function imageResize(?int $width = null, ?int $height = null, string $mode = 'lfit'): static
    {
        $params = "m_{$mode}";
        if ($width !== null) {
            $params .= ",w_{$width}";
        }
        if ($height !== null) {
            $params .= ",h_{$height}";
        }

        return $this->process("image/resize,{$params}");
    }

    /**
     * 按百分比缩放图片。
     *
     * @param int $percent 缩放百分比，范围 [1, 1000]，100 为原始大小
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/resize-images-4
     */
    public function imageResizeByPercent(int $percent): static
    {
        return $this->process("image/resize,p_{$percent}");
    }

    /**
     * 自定义裁剪图片。
     *
     * @param int $width 裁剪宽度，单位像素
     * @param int $height 裁剪高度，单位像素
     * @param string $gravity 裁剪起点位置，枚举值：
     *   - `nw`：左上
     *   - `north`：上中
     *   - `ne`：右上
     *   - `west`：左中
     *   - `center`：中心（默认）
     *   - `east`：右中
     *   - `sw`：左下
     *   - `south`：下中
     *   - `se`：右下
     * @param int $x 水平偏移量，单位像素，默认 0
     * @param int $y 垂直偏移量，单位像素，默认 0
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/custom-crop
     */
    public function imageCrop(int $width, int $height, string $gravity = 'center', int $x = 0, int $y = 0): static
    {
        $params = "w_{$width},h_{$height},g_{$gravity}";
        if ($x > 0) {
            $params .= ",x_{$x}";
        }
        if ($y > 0) {
            $params .= ",y_{$y}";
        }

        return $this->process("image/crop,{$params}");
    }

    /**
     * 旋转图片。
     *
     * @param int $angle 旋转角度，范围 [0, 360]，顺时针旋转
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/rotate-images
     */
    public function imageRotate(int $angle): static
    {
        return $this->process("image/rotate,{$angle}");
    }

    /**
     * 翻转图片。
     *
     * @param string $direction 翻转方向，枚举值：
     *   - `h` 或 `horizontal`：水平翻转（默认）
     *   - `v` 或 `vertical`：垂直翻转
     *   - `both`：双向翻转
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/flip-images
     */
    public function imageFlip(string $direction = 'h'): static
    {
        $value = match ($direction) {
            'v', 'vertical' => 0,
            'both' => 2,
            default => 1,
        };

        return $this->process("image/flip,{$value}");
    }

    /**
     * 图片格式转换。
     *
     * @param string $format 目标格式，枚举值：`jpg`、`png`、`webp`、`bmp`、`gif`、`tiff`、`heic`、`avif`
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/format-conversion
     */
    public function imageFormat(string $format): static
    {
        return $this->process("image/format,{$format}");
    }

    /**
     * 图片质量变换。
     *
     * @param int $q 质量值，范围 [1, 100]
     * @param bool $absolute 是否为绝对质量。false（默认）为相对质量（原图的百分比），true 为绝对质量
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/quality-transformation
     */
    public function imageQuality(int $q, bool $absolute = false): static
    {
        $key = $absolute ? 'Q' : 'q';

        return $this->process("image/quality,{$key}_{$q}");
    }

    /**
     * 图片模糊效果。
     *
     * @param int $radius 模糊半径，范围 [1, 50]
     * @param int $sigma 模糊标准差，范围 [1, 50]
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/blur
     */
    public function imageBlur(int $radius, int $sigma): static
    {
        return $this->process("image/blur,r_{$radius},s_{$sigma}");
    }

    /**
     * 图片亮度调节。
     *
     * @param int $value 亮度值，范围 [-100, 100]，负值变暗，正值变亮
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/brightness
     */
    public function imageBright(int $value): static
    {
        return $this->process("image/bright,{$value}");
    }

    /**
     * 图片对比度调节。
     *
     * @param int $value 对比度值，范围 [-100, 100]
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/contrast
     */
    public function imageContrast(int $value): static
    {
        return $this->process("image/contrast,{$value}");
    }

    /**
     * 图片锐化。
     *
     * @param int $value 锐化值，范围 [50, 399]，推荐 100
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/sharpen
     */
    public function imageSharpen(int $value): static
    {
        return $this->process("image/sharpen,{$value}");
    }

    /**
     * 内切圆裁剪，将图片裁剪为圆形。建议配合 imageFormat('png') 使用以保留透明背景。
     *
     * @param int $radius 内切圆半径，范围 [1, 4096]，单位像素
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/circle-crop
     */
    public function imageCircle(int $radius): static
    {
        return $this->process("image/circle,r_{$radius}");
    }

    /**
     * 圆角矩形裁剪。建议配合 imageFormat('png') 使用以保留透明背景。
     *
     * @param int $radius 圆角半径，范围 [1, 4096]，单位像素
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/rounded-rectangle
     */
    public function imageRoundedCorners(int $radius): static
    {
        return $this->process("image/rounded-corners,r_{$radius}");
    }

    /**
     * 自适应方向，根据图片 EXIF 信息自动旋转图片到正确方向。
     *
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/auto-orient
     */
    public function imageAutoOrient(): static
    {
        return $this->process('image/auto-orient,1');
    }

    /**
     * 渐进显示，仅对 JPG 格式有效。开启后图片加载时从模糊到清晰渐进显示。
     *
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/progressive-display
     */
    public function imageInterlace(): static
    {
        return $this->process('image/interlace,1');
    }

    /**
     * 索引切割，将图片按指定轴方向分割后取指定索引的部分。
     *
     * @param int $size 每份的大小，单位像素
     * @param int $index 切割后的索引号，从 0 开始
     * @param string $axis 切割轴方向，枚举值：
     *   - `x`：水平切割（默认）
     *   - `y`：垂直切割
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/indexed-cut
     */
    public function imageIndexCrop(int $size, int $index, string $axis = 'x'): static
    {
        return $this->process("image/indexcrop,{$axis}_{$size},i_{$index}");
    }

    /**
     * 添加文字水印。
     *
     * @param string $text 水印文字内容
     * @param int $size 文字大小，单位像素，默认 40
     * @param string $color 文字颜色，6 位十六进制 RGB 值（不含 #），默认 'FFFFFF'（白色）
     * @param string $position 水印位置，枚举值：
     *   - `nw`：左上
     *   - `north`：上中
     *   - `ne`：右上
     *   - `west`：左中
     *   - `center`：中心
     *   - `east`：右中
     *   - `sw`：左下
     *   - `south`：下中
     *   - `se`：右下（默认）
     * @param int $x 水平偏移量，单位像素，默认 10
     * @param int $y 垂直偏移量，单位像素，默认 10
     * @param int $transparency 透明度，范围 [0, 100]，0 为完全透明，100 为完全不透明，默认 100
     * @param string|null $font 字体名称，URL 安全 Base64 编码（内部自动处理）
     * @param int|null $shadow 文字阴影透明度，范围 [0, 100]
     * @param int|null $rotate 文字旋转角度，范围 [0, 360]
     * @param bool $fill 是否平铺水印，默认 false
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/add-watermarks
     */
    public function imageWatermarkText(
        string $text,
        int $size = 40,
        string $color = 'FFFFFF',
        string $position = 'se',
        int $x = 10,
        int $y = 10,
        int $transparency = 100,
        ?string $font = null,
        ?int $shadow = null,
        ?int $rotate = null,
        bool $fill = false
    ): static {
        $encodedText = OssAdapter::watermarkText($text);
        $params = "text_{$encodedText},size_{$size},color_{$color},g_{$position},x_{$x},y_{$y},t_{$transparency}";

        if ($font !== null) {
            $params .= ',type_' . OssAdapter::watermarkText($font);
        }
        if ($shadow !== null) {
            $params .= ",shadow_{$shadow}";
        }
        if ($rotate !== null) {
            $params .= ",rotate_{$rotate}";
        }
        if ($fill) {
            $params .= ',fill_1';
        }

        return $this->process("image/watermark,{$params}");
    }

    /**
     * 添加图片水印。
     *
     * @param string $imagePath OSS 上的水印图片路径，URL 安全 Base64 编码（内部自动处理）
     * @param string $position 水印位置，枚举值：
     *   - `nw`：左上
     *   - `north`：上中
     *   - `ne`：右上
     *   - `west`：左中
     *   - `center`：中心
     *   - `east`：右中
     *   - `sw`：左下
     *   - `south`：下中
     *   - `se`：右下（默认）
     * @param int $x 水平偏移量，单位像素，默认 10
     * @param int $y 垂直偏移量，单位像素，默认 10
     * @param int $transparency 透明度，范围 [0, 100]，默认 100
     * @param int|null $percent 水印图片缩放百分比，范围 [1, 100]，相对于原图
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/add-watermarks
     */
    public function imageWatermarkImage(
        string $imagePath,
        string $position = 'se',
        int $x = 10,
        int $y = 10,
        int $transparency = 100,
        ?int $percent = null
    ): static {
        $encodedImage = OssAdapter::watermarkText($imagePath);
        $params = "image_{$encodedImage},g_{$position},x_{$x},y_{$y},t_{$transparency}";

        if ($percent !== null) {
            $params .= ",P_{$percent}";
        }

        return $this->process("image/watermark,{$params}");
    }

    /**
     * 获取图片基本信息，返回 JSON。不能与其他图片操作组合使用。
     *
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/obtain-image-information
     */
    public function imageInfo(): static
    {
        return $this->process('image/info');
    }

    /**
     * 获取图片主色调，返回 `0xRRGGBB` 格式。不能与其他图片操作组合使用。
     *
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/average-hue
     */
    public function imageAverageHue(): static
    {
        return $this->process('image/average-hue');
    }

    // ---- 图片移除 ----

    /** 移除图片处理链中的 resize（缩放）操作。 */
    public function imageRemoveResize(): static
    {
        return $this->removeOperation('resize');
    }

    /** 移除图片处理链中的 crop（裁剪）操作。 */
    public function imageRemoveCrop(): static
    {
        return $this->removeOperation('crop');
    }

    /** 移除图片处理链中的 rotate（旋转）操作。 */
    public function imageRemoveRotate(): static
    {
        return $this->removeOperation('rotate');
    }

    /** 移除图片处理链中的 flip（翻转）操作。 */
    public function imageRemoveFlip(): static
    {
        return $this->removeOperation('flip');
    }

    /** 移除图片处理链中的 format（格式转换）操作。 */
    public function imageRemoveFormat(): static
    {
        return $this->removeOperation('format');
    }

    /** 移除图片处理链中的 quality（质量变换）操作。 */
    public function imageRemoveQuality(): static
    {
        return $this->removeOperation('quality');
    }

    /** 移除图片处理链中的 blur（模糊）操作。 */
    public function imageRemoveBlur(): static
    {
        return $this->removeOperation('blur');
    }

    /** 移除图片处理链中的 bright（亮度）操作。 */
    public function imageRemoveBright(): static
    {
        return $this->removeOperation('bright');
    }

    /** 移除图片处理链中的 contrast（对比度）操作。 */
    public function imageRemoveContrast(): static
    {
        return $this->removeOperation('contrast');
    }

    /** 移除图片处理链中的 sharpen（锐化）操作。 */
    public function imageRemoveSharpen(): static
    {
        return $this->removeOperation('sharpen');
    }

    /** 移除图片处理链中的 circle（内切圆裁剪）操作。 */
    public function imageRemoveCircle(): static
    {
        return $this->removeOperation('circle');
    }

    /** 移除图片处理链中的 rounded-corners（圆角矩形）操作。 */
    public function imageRemoveRoundedCorners(): static
    {
        return $this->removeOperation('rounded-corners');
    }

    /** 移除图片处理链中的 auto-orient（自适应方向）操作。 */
    public function imageRemoveAutoOrient(): static
    {
        return $this->removeOperation('auto-orient');
    }

    /** 移除图片处理链中的 interlace（渐进显示）操作。 */
    public function imageRemoveInterlace(): static
    {
        return $this->removeOperation('interlace');
    }

    /** 移除图片处理链中的 indexcrop（索引切割）操作。 */
    public function imageRemoveIndexCrop(): static
    {
        return $this->removeOperation('indexcrop');
    }

    /** 移除图片处理链中的 watermark（水印）操作。 */
    public function imageRemoveWatermark(): static
    {
        return $this->removeOperation('watermark');
    }

    // ==================== 视频处理 video/ ====================

    /**
     * 视频截帧，从视频中截取指定时间点的画面。
     *
     * @param int $timeMs 截帧时间点，单位毫秒
     * @param int|null $width 截帧图片宽度，单位像素
     * @param int|null $height 截帧图片高度，单位像素
     * @param string $format 输出格式，枚举值：`jpg`（默认）、`png`
     * @param string|null $mode 截帧模式，枚举值：
     *   - `fast`：取最近的关键帧，速度快但不精确
     *   - null：精确截取指定时间的帧（默认）
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/video-snapshots
     */
    public function videoSnapshot(
        int $timeMs,
        ?int $width = null,
        ?int $height = null,
        string $format = 'jpg',
        ?string $mode = null
    ): static {
        $params = "t_{$timeMs},f_{$format}";
        if ($width !== null) {
            $params .= ",w_{$width}";
        }
        if ($height !== null) {
            $params .= ",h_{$height}";
        }
        if ($mode !== null) {
            $params .= ",m_{$mode}";
        }

        return $this->process("video/snapshot,{$params}");
    }

    /**
     * 获取视频元信息，返回 JSON 格式的视频基本信息。
     *
     * @return static
     */
    public function videoInfo(): static
    {
        return $this->process('video/info');
    }

    /**
     * 视频转码（异步操作）。
     *
     * @param string $format 目标格式，枚举值：`mp4`、`flv`、`avi`、`m3u8` 等
     * @param string|null $vcodec 视频编码，枚举值：`h264`、`h265`、`copy`
     * @param string|null $acodec 音频编码，枚举值：`aac`、`mp3`、`copy`
     * @param string|null $resolution 分辨率，如 `1280x720`
     * @param int|null $videoBitrate 视频比特率，单位 kbps
     * @param int|null $audioBitrate 音频比特率，单位 kbps
     * @param float|null $fps 帧率
     * @param int|null $startMs 起始时间，单位毫秒
     * @param int|null $durationMs 持续时间，单位毫秒
     * @return static
     */
    public function videoConvert(
        string $format,
        ?string $vcodec = null,
        ?string $acodec = null,
        ?string $resolution = null,
        ?int $videoBitrate = null,
        ?int $audioBitrate = null,
        ?float $fps = null,
        ?int $startMs = null,
        ?int $durationMs = null
    ): static {
        $params = "f_{$format}";
        if ($vcodec !== null) {
            $params .= ",vcodec_{$vcodec}";
        }
        if ($acodec !== null) {
            $params .= ",acodec_{$acodec}";
        }
        if ($resolution !== null) {
            $params .= ",s_{$resolution}";
        }
        if ($videoBitrate !== null) {
            $params .= ",vb_{$videoBitrate}";
        }
        if ($audioBitrate !== null) {
            $params .= ",ab_{$audioBitrate}";
        }
        if ($fps !== null) {
            $params .= ",fps_{$fps}";
        }
        if ($startMs !== null) {
            $params .= ",ss_{$startMs}";
        }
        if ($durationMs !== null) {
            $params .= ",t_{$durationMs}";
        }

        return $this->asyncProcess("video/convert,{$params}");
    }

    /**
     * 视频转 GIF 动图（异步操作）。
     *
     * @param int $startMs 起始时间，单位毫秒
     * @param int $durationMs 持续时间，单位毫秒
     * @param int|null $width GIF 宽度，单位像素
     * @param int|null $height GIF 高度，单位像素
     * @param float|null $fps 帧率
     * @return static
     */
    public function videoGif(
        int $startMs,
        int $durationMs,
        ?int $width = null,
        ?int $height = null,
        ?float $fps = null
    ): static {
        $params = "ss_{$startMs},t_{$durationMs}";
        if ($width !== null) {
            $params .= ",w_{$width}";
        }
        if ($height !== null) {
            $params .= ",h_{$height}";
        }
        if ($fps !== null) {
            $params .= ",fps_{$fps}";
        }

        return $this->asyncProcess("video/gif,{$params}");
    }

    /**
     * 视频雪碧图（异步操作），将视频按固定间隔截帧并拼合为一张大图。
     *
     * @param int $interval 截帧间隔，单位秒
     * @param int $columns 雪碧图列数
     * @param int $rows 雪碧图行数
     * @param int|null $width 每帧宽度，单位像素
     * @param int|null $height 每帧高度，单位像素
     * @return static
     */
    public function videoSprite(int $interval, int $columns, int $rows, ?int $width = null, ?int $height = null): static
    {
        $params = "interval_{$interval},columns_{$columns},rows_{$rows}";
        if ($width !== null) {
            $params .= ",w_{$width}";
        }
        if ($height !== null) {
            $params .= ",h_{$height}";
        }

        return $this->asyncProcess("video/sprite,{$params}");
    }

    /**
     * 视频拼接（异步操作），将多个视频拼接为一个。
     *
     * @param array $sources OSS 上的视频路径数组
     * @return static
     */
    public function videoConcat(array $sources): static
    {
        $parts = [];
        foreach ($sources as $source) {
            $parts[] = 'source_' . OssAdapter::watermarkText($source);
        }

        return $this->asyncProcess('video/concat,' . implode(',', $parts));
    }

    // ---- 视频移除 ----

    /** 移除视频处理链中的 snapshot（截帧）操作。 */
    public function videoRemoveSnapshot(): static
    {
        return $this->removeOperation('snapshot');
    }

    /** 移除视频处理链中的 convert（转码）操作。 */
    public function videoRemoveConvert(): static
    {
        return $this->removeOperation('convert');
    }

    /** 移除视频处理链中的 gif（转 GIF）操作。 */
    public function videoRemoveGif(): static
    {
        return $this->removeOperation('gif');
    }

    /** 移除视频处理链中的 sprite（雪碧图）操作。 */
    public function videoRemoveSprite(): static
    {
        return $this->removeOperation('sprite');
    }

    /** 移除视频处理链中的 concat（拼接）操作。 */
    public function videoRemoveConcat(): static
    {
        return $this->removeOperation('concat');
    }

    // ==================== 音频处理 audio/ ====================

    /**
     * 获取音频元信息，返回 JSON 格式的音频基本信息。
     *
     * @return static
     */
    public function audioInfo(): static
    {
        return $this->process('audio/info');
    }

    /**
     * 音频转码（异步操作）。
     *
     * @param string $format 目标格式，枚举值：`mp3`、`aac`、`flac`、`wav`、`ogg` 等
     * @param int|null $sampleRate 采样率，如 44100
     * @param int|null $channels 声道数，枚举值：1（单声道）、2（立体声）
     * @param int|null $bitrate 比特率，单位 kbps
     * @param int|null $startMs 起始时间，单位毫秒
     * @param int|null $durationMs 持续时间，单位毫秒
     * @return static
     */
    public function audioConvert(
        string $format,
        ?int $sampleRate = null,
        ?int $channels = null,
        ?int $bitrate = null,
        ?int $startMs = null,
        ?int $durationMs = null
    ): static {
        $params = "f_{$format}";
        if ($sampleRate !== null) {
            $params .= ",ar_{$sampleRate}";
        }
        if ($channels !== null) {
            $params .= ",ac_{$channels}";
        }
        if ($bitrate !== null) {
            $params .= ",ab_{$bitrate}";
        }
        if ($startMs !== null) {
            $params .= ",ss_{$startMs}";
        }
        if ($durationMs !== null) {
            $params .= ",t_{$durationMs}";
        }

        return $this->asyncProcess("audio/convert,{$params}");
    }

    /**
     * 音频拼接（异步操作），将多个音频拼接为一个。
     *
     * @param array $sources OSS 上的音频路径数组
     * @return static
     */
    public function audioConcat(array $sources): static
    {
        $parts = [];
        foreach ($sources as $source) {
            $parts[] = 'source_' . OssAdapter::watermarkText($source);
        }

        return $this->asyncProcess('audio/concat,' . implode(',', $parts));
    }

    // ---- 音频移除 ----

    /** 移除音频处理链中的 convert（转码）操作。 */
    public function audioRemoveConvert(): static
    {
        return $this->removeOperation('convert');
    }

    /** 移除音频处理链中的 concat（拼接）操作。 */
    public function audioRemoveConcat(): static
    {
        return $this->removeOperation('concat');
    }

    // ==================== 文档处理 doc/ ====================

    /**
     * 文档在线预览。
     *
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/document-preview
     */
    public function docPreview(): static
    {
        return $this->process('doc/preview');
    }

    /**
     * 文档在线编辑（WebOffice）。
     *
     * @return static
     */
    public function docEdit(): static
    {
        return $this->process('doc/edit');
    }

    /**
     * 文档截图，将文档指定页面渲染为图片。
     *
     * @param int|null $page 指定页码，null 为默认第一页
     * @return static
     */
    public function docSnapshot(?int $page = null): static
    {
        $params = 'doc/snapshot';
        if ($page !== null) {
            $params .= ",page_{$page}";
        }

        return $this->process($params);
    }

    /**
     * 文档格式转换（异步操作）。
     *
     * @param string $target 目标格式，枚举值：`pdf`、`png`、`jpg`、`txt`
     * @param string|null $source 源文件格式，枚举值：`docx`、`doc`、`pptx`、`ppt`、`pdf`、`xlsx`、`xls`
     * @param string|null $pages 要转换的页码范围，如 `1,2,4-10`
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/document-conversion
     */
    public function docConvert(string $target, ?string $source = null, ?string $pages = null): static
    {
        $params = "target_{$target}";
        if ($source !== null) {
            $params .= ",source_{$source}";
        }
        if ($pages !== null) {
            $params .= ",pages_{$pages}";
        }

        return $this->asyncProcess("doc/convert,{$params}");
    }

    /**
     * 智能文档翻译。
     *
     * @param string $content 要翻译的内容，URL 安全 Base64 编码（内部自动处理）
     * @param string $language 目标语言，枚举值：
     *   - `zh_CN`：中文（默认）
     *   - `en_US`：英文
     *   - `ja_JP`：日文
     *   - `fr_FR`：法文
     * @param string|null $format 响应格式，枚举值：
     *   - `json`：JSON 格式（默认）
     *   - `event-stream`：SSE 流式格式
     * @return static
     *
     * @see https://help.aliyun.com/zh/oss/user-guide/smart-document-translation
     */
    public function docTranslate(string $content, string $language = 'zh_CN', ?string $format = null): static
    {
        $encoded = OssAdapter::watermarkText($content);
        $params = "language_{$language},content_{$encoded}";
        if ($format !== null) {
            $params .= ",format_{$format}";
        }

        return $this->process("doc/translate,{$params}");
    }

    // ---- 文档移除 ----

    /** 移除文档处理链中的 preview（在线预览）操作。 */
    public function docRemovePreview(): static
    {
        return $this->removeOperation('preview');
    }

    /** 移除文档处理链中的 edit（在线编辑）操作。 */
    public function docRemoveEdit(): static
    {
        return $this->removeOperation('edit');
    }

    /** 移除文档处理链中的 snapshot（截图）操作。 */
    public function docRemoveSnapshot(): static
    {
        return $this->removeOperation('snapshot');
    }

    /** 移除文档处理链中的 convert（格式转换）操作。 */
    public function docRemoveConvert(): static
    {
        return $this->removeOperation('convert');
    }

    /** 移除文档处理链中的 translate（翻译）操作。 */
    public function docRemoveTranslate(): static
    {
        return $this->removeOperation('translate');
    }

    // ==================== 异步辅助 ====================

    /**
     * 指定异步处理结果的保存路径，配合异步操作（转码、拼接等）使用。
     *
     * @param string $bucket 目标 Bucket 名称
     * @param string $key 目标对象路径
     * @return static
     */
    public function saveas(string $bucket, string $key): static
    {
        $encoded = OssAdapter::watermarkText("{$bucket}/{$key}");

        return $this->process("sys/saveas,o_{$encoded}");
    }

    /**
     * 设置异步处理结果的通知回调，通过消息队列接收处理完成通知。
     *
     * @param string $topic 消息队列主题名
     * @return static
     */
    public function notify(string $topic): static
    {
        $encoded = OssAdapter::watermarkText($topic);

        return $this->process("sys/notify,topic_{$encoded}");
    }

    // ==================== 内部方法 ====================

    private function convertTo(?string $domain, ?string $baseUrl): ?static
    {
        if ($domain === null) {
            return null;
        }

        $scheme = 'https';
        if ($baseUrl !== null) {
            $parsed = HUrl::parse($baseUrl);
            if ($parsed instanceof HUrl) {
                $scheme = $parsed->getScheme();
            }
        }

        /** @var static $new */
        $new = $this->withHost($domain)->withScheme($scheme);

        return $new;
    }

    private function appendAction(string $paramName, string $action): static
    {
        $current = $this->extractQueryParam($paramName);

        if (empty($current)) {
            return $this->setQueryRaw($paramName, $action);
        }

        $slashPos = strpos($action, '/');
        if ($slashPos !== false) {
            $newPrefix = substr($action, 0, $slashPos);
            $currentPrefix = $this->getFirstPrefix($current);

            if ($newPrefix === $currentPrefix) {
                $operation = substr($action, $slashPos + 1);
                return $this->setQueryRaw($paramName, $current . '/' . $operation);
            }
        }

        return $this->setQueryRaw($paramName, $current . '/' . $action);
    }

    private function removeOperation(string $operationName): static
    {
        $instance = $this;

        // 在两个参数名中都尝试移除
        foreach (['x-oss-process', 'x-oss-async-process'] as $paramName) {
            $current = $instance->extractQueryParam($paramName);
            if (empty($current)) {
                continue;
            }

            $parts = explode('/', $current);
            $filtered = [];

            foreach ($parts as $part) {
                if (in_array($part, ['image', 'video', 'doc', 'audio', 'sys'], true)) {
                    $filtered[] = $part;
                    continue;
                }

                $commaPos = strpos($part, ',');
                $name = $commaPos !== false ? substr($part, 0, $commaPos) : $part;

                if ($name !== $operationName) {
                    $filtered[] = $part;
                }
            }

            $result = implode('/', $filtered);
            $result = preg_replace('#(image|video|doc|audio|sys)/?$#', '', $result);
            $result = rtrim($result, '/');

            if (empty($result)) {
                $instance = $instance->removeQueryRaw($paramName);
            } else {
                $instance = $instance->setQueryRaw($paramName, $result);
            }
        }

        return $instance;
    }

    private function getFirstPrefix(string $processValue): string
    {
        $pos = strpos($processValue, '/');

        return $pos !== false ? substr($processValue, 0, $pos) : $processValue;
    }

    /**
     * 从 raw query 中提取指定参数值
     */
    private function extractQueryParam(string $name): string
    {
        $query = $this->getQuery();
        if (empty($query)) {
            return '';
        }

        foreach (explode('&', $query) as $part) {
            if (strpos($part, $name . '=') === 0) {
                return substr($part, strlen($name) + 1);
            }
        }

        return '';
    }

    /**
     * 设置 raw query 参数（不 urlencode）
     */
    private function setQueryRaw(string $name, string $value): static
    {
        $query = $this->getQuery();
        $parts = [];

        if (!empty($query)) {
            foreach (explode('&', $query) as $part) {
                if (strpos($part, $name . '=') !== 0) {
                    $parts[] = $part;
                }
            }
        }

        $parts[] = $name . '=' . $value;

        /** @var static $new */
        $new = $this->withQuery(implode('&', array_filter($parts)));

        return $new;
    }

    /**
     * 移除 raw query 参数
     */
    private function removeQueryRaw(string $name): static
    {
        $query = $this->getQuery();
        if (empty($query)) {
            return $this;
        }

        $parts = [];
        foreach (explode('&', $query) as $part) {
            if (strpos($part, $name . '=') !== 0) {
                $parts[] = $part;
            }
        }

        /** @var static $new */
        $new = $this->withQuery(implode('&', $parts));

        return $new;
    }
}
