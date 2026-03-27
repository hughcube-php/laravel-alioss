# Laravel AliOSS

<p>
    <a href="https://github.com/hughcube-php/laravel-alioss/actions?query=workflow%3ATest">
        <img src="https://github.com/hughcube-php/laravel-alioss/workflows/Test/badge.svg" alt="Test Actions status">
    </a>
    <a href="https://styleci.io/repos/473234744">
        <img src="https://github.styleci.io/repos/473234744/shield?branch=master" alt="StyleCI">
    </a>
    <a href="https://packagist.org/packages/hughcube/laravel-alioss">
        <img src="https://poser.pugx.org/hughcube/laravel-alioss/v/stable" alt="Latest Stable Version">
    </a>
    <a href="https://packagist.org/packages/hughcube/laravel-alioss">
        <img src="https://poser.pugx.org/hughcube/laravel-alioss/license" alt="License">
    </a>
</p>

Alibaba Cloud OSS adapter for Laravel, based on [alibabacloud/oss-v2](https://github.com/aliyun/alibabacloud-oss-php-sdk-v2).

## Requirements

- PHP >= 8.0
- Laravel 9+

## Installation

```bash
composer require hughcube/laravel-alioss
```

ServiceProvider auto-discovery is enabled. No manual registration needed.

## Configuration

Add a disk to `config/filesystems.php`:

```php
'disks' => [
    'oss' => [
        'driver'          => 'alioss',
        'bucket'          => env('ALIOSS_BUCKET'),
        'region'          => env('ALIOSS_REGION', 'cn-shanghai'),
        'accessKeyId'     => env('ALIOSS_ACCESS_KEY_ID'),
        'accessKeySecret' => env('ALIOSS_ACCESS_KEY_SECRET'),
        'endpoint'        => env('ALIOSS_ENDPOINT'),          // optional
        'prefix'          => env('ALIOSS_PREFIX', ''),         // optional
        'internal'        => env('ALIOSS_INTERNAL', false),    // use internal endpoint
        'isCName'         => false,                            // optional
        'securityToken'   => null,                             // optional, STS token
        'requestProxy'    => null,                             // optional
        'acl'             => null,                             // optional, default ACL
        'cdnBaseUrl'      => env('ALIOSS_CDN_BASE_URL'),      // optional
        'uploadBaseUrl'   => env('ALIOSS_UPLOAD_BASE_URL'),   // optional
    ],
],
```

## Quick Start

```php
use HughCube\Laravel\AliOSS\AliOSS;

$adapter = AliOSS::getClient();     // or AliOSS::getClient('oss')

// Write
$adapter->write('path/file.txt', 'content');

// Read
$content = $adapter->read('path/file.txt');

// URL
$url = $adapter->cdnUrl('path/file.jpg');
echo $url;  // https://cdn.example.com/path/file.jpg
```

## Flysystem Operations

Standard `FilesystemAdapter` interface:

```php
$adapter->write('file.txt', 'content');
$adapter->writeStream('file.txt', $stream);
$adapter->read('file.txt');
$adapter->readStream('file.txt');
$adapter->delete('file.txt');
$adapter->fileExists('file.txt');
$adapter->copy('source.txt', 'dest.txt');
$adapter->move('source.txt', 'dest.txt');
$adapter->createDirectory('dir');
$adapter->setVisibility('file.txt', 'public');
$adapter->visibility('file.txt');
$adapter->fileAttributes('file.txt');
```

Extended operations:

```php
// Upload local file, returns OssUrl
$url = $adapter->writeFile('/tmp/photo.jpg', 'photos/photo.jpg');

// Download from URL and upload to OSS (stream, supports large files)
$url = $adapter->writeFromUrl('https://example.com/photo.jpg', 'photos/photo.jpg');

// Download to local file
$adapter->download('photos/photo.jpg', '/tmp/photo.jpg');

// Create symlink
$adapter->symlink('link.jpg', 'target.jpg');

// WeChat avatar scenario: upload only when URL changed
$url = $adapter->mirrorIfChanged($wechatAvatarUrl, $existingDbUrl, 'avatars');
```

## URL Operations

All URL methods return `OssUrl` instances (extends `HUrl`, supports `__toString`).

### Build URLs

```php
$adapter->url('file.jpg');              // OSS URL (default)
$adapter->cdnUrl('file.jpg');           // CDN URL, null if not configured
$adapter->uploadUrl('file.jpg');        // Upload URL
$adapter->ossUrl('file.jpg');           // OSS external URL
$adapter->ossInternalUrl('file.jpg');   // OSS internal URL
$adapter->ossUri('file.jpg');           // oss://bucket/file.jpg
```

### Signed URLs

```php
$adapter->signUrl('file.jpg', 60);          // GET signed, 60s
$adapter->signUploadUrl('file.jpg', 60);    // PUT signed, 60s
$adapter->presign('file.jpg', 60, 'HEAD');  // PresignResult object
```

### Domain Detection

```php
$adapter->isCdnUrl($url);
$adapter->isUploadUrl($url);
$adapter->isOssUrl($url);
$adapter->isOssInternalUrl($url);
$adapter->isBucketUrl($url);     // any of the above
```

### Domain Conversion

```php
$adapter->toCdnUrl($url);
$adapter->toUploadUrl($url);
$adapter->toOssUrl($url);
$adapter->toOssInternalUrl($url);
```

### Parse URL

```php
$ossUrl = $adapter->parseUrl('https://cdn.example.com/path/file.jpg');
```

## OssUrl

`OssUrl` extends `HUrl` with OSS-specific capabilities. All methods are immutable (return new instances).

### Domain Operations

```php
$url = $adapter->cdnUrl('photo.jpg');

$url->toCdn();           // switch to CDN domain
$url->toUpload();        // switch to upload domain
$url->toOss();           // switch to OSS domain
$url->toOssInternal();   // switch to internal domain
$url->toOssUri();        // "oss://bucket/key"

$url->isCdn();           // true/false
$url->isUpload();
$url->isOss();
$url->isOssInternal();
$url->isBucket();        // any known domain
```

### Signing

```php
$url->sign(60);               // GET signed URL
$url->sign(60, 'PUT');        // PUT signed URL
$url->sign(60, 'HEAD');       // HEAD signed URL
$url->signUpload(60);         // shortcut for sign(60, 'PUT')
```

Process parameters are included in the signature automatically:

```php
$adapter->ossUrl('photo.jpg')
    ->imageResize(800)
    ->imageFormat('webp')
    ->sign(300);
// x-oss-process is signed into the URL
```

### Image Processing

Operations chain automatically. `image/` prefix appears only once in the output.

```php
$url = $adapter->cdnUrl('photo.jpg')
    ->imageResize(800, 600, 'fill')
    ->imageRotate(90)
    ->imageWatermarkText('Copyright', size: 30, color: 'FF0000')
    ->imageFormat('webp')
    ->imageQuality(85);

echo $url;
// https://cdn.example.com/photo.jpg?x-oss-process=image/resize,m_fill,w_800,h_600/rotate,90/watermark,.../format,webp/quality,q_85
```

| Method | Description |
|--------|-------------|
| `imageResize($w, $h, $mode)` | Scale. Modes: `lfit`, `mfit`, `fill`, `pad`, `fixed` |
| `imageResizeByPercent($pct)` | Scale by percentage [1, 1000] |
| `imageCrop($w, $h, $gravity, $x, $y)` | Crop. Gravity: `nw`, `north`, `ne`, `west`, `center`, `east`, `sw`, `south`, `se` |
| `imageRotate($angle)` | Rotate [0, 360] |
| `imageFlip($dir)` | Flip: `h`, `v`, `both` |
| `imageFormat($fmt)` | Convert: `jpg`, `png`, `webp`, `bmp`, `gif`, `tiff`, `heic`, `avif` |
| `imageQuality($q, $abs)` | Quality [1, 100]. `$abs=true` for absolute |
| `imageBlur($r, $s)` | Blur. radius/sigma [1, 50] |
| `imageBright($val)` | Brightness [-100, 100] |
| `imageContrast($val)` | Contrast [-100, 100] |
| `imageSharpen($val)` | Sharpen [50, 399] |
| `imageCircle($r)` | Circle crop, radius [1, 4096] |
| `imageRoundedCorners($r)` | Rounded corners [1, 4096] |
| `imageAutoOrient()` | Auto-rotate by EXIF |
| `imageInterlace()` | Progressive display (JPG only) |
| `imageIndexCrop($size, $idx, $axis)` | Indexed slice |
| `imageWatermarkText($text, ...)` | Text watermark |
| `imageWatermarkImage($path, ...)` | Image watermark |
| `imageInfo()` | Image metadata (JSON) |
| `imageAverageHue()` | Dominant color |

Remove operations: `imageRemoveResize()`, `imageRemoveRotate()`, `imageRemoveWatermark()`, etc.

### Video Processing

```php
// Snapshot (sync)
$adapter->ossUrl('video.mp4')->videoSnapshot(1000, 800, 600);

// Video info (sync)
$adapter->ossUrl('video.mp4')->videoInfo();

// Transcode (async)
$adapter->ossUrl('video.mp4')
    ->videoConvert('mp4', 'h264', 'aac', '1280x720')
    ->saveas('bucket', 'output.mp4')
    ->notify('my-topic');

// GIF (async)
$adapter->ossUrl('video.mp4')->videoGif(5000, 3000, 320, 240);

// Sprite sheet (async)
$adapter->ossUrl('video.mp4')->videoSprite(5, 10, 10, 160, 90);

// Concat (async)
$adapter->ossUrl('video1.mp4')->videoConcat(['video2.mp4', 'video3.mp4']);
```

### Audio Processing

```php
$adapter->ossUrl('audio.mp3')->audioInfo();
$adapter->ossUrl('audio.wav')->audioConvert('mp3', 44100, 2, 320);
$adapter->ossUrl('audio1.mp3')->audioConcat(['audio2.mp3']);
```

### Document Processing

```php
$adapter->ossUrl('doc.docx')->docPreview();
$adapter->ossUrl('doc.docx')->docEdit();
$adapter->ossUrl('doc.docx')->docSnapshot(1);
$adapter->ossUrl('doc.docx')
    ->docConvert('pdf', 'docx', '1,2,4-10')
    ->saveas('bucket', 'output.pdf');
$adapter->ossUrl('doc.docx')->docTranslate('Hello', 'zh_CN');
```

### Generic Process

```php
$url->process('image/resize,w_800/rotate,90');      // sync
$url->asyncProcess('video/convert,f_mp4');           // async
$url->clearProcess();                                 // clear all
```

## Validation Rule

`OssFile` validates that a URL points to a valid OSS file.

```php
use HughCube\Laravel\AliOSS\Rules\OssFile;

$rules = [
    'avatar' => [
        'required',
        OssFile::make()
            ->cdnDomain()
            ->image()
            ->maxSize(2 * 1024 * 1024)
            ->maxWidth(4096)
            ->maxHeight(4096)
            ->aspectRatio(1, 1),
    ],

    'document' => [
        'required',
        OssFile::make()->anyDomain()->document()->maxSize(10 * 1024 * 1024),
    ],

    'attachment' => [
        'required',
        OssFile::make()
            ->extensions(['pdf', 'docx', 'xlsx'])
            ->exceptExtensions(['exe', 'php'])
            ->directory('uploads')
            ->filenameMaxLength(100),
    ],
];
```

### Constraints

**Domain:** `cdnDomain()`, `uploadDomain()`, `ossDomain()`, `anyDomain()`

**File type:** `image()`, `video()`, `audio()`, `media()`, `document()`, `pdf()`, `word()`, `excel()`, `ppt()`, `archive()`, `text()`, `json()`, `xml()`, `mimeTypes([...])`

**Size:** `minSize($bytes)`, `maxSize($bytes)`, `sizeBetween($min, $max)`

**Image dimensions:** `maxWidth($px)`, `maxHeight($px)`, `aspectRatio($w, $h)`, `minAspectRatio($w, $h)`, `maxAspectRatio($w, $h)`

**Path:** `extensions([...])`, `exceptExtensions([...])`, `directory($dir)`, `directories([...])`, `exceptDirectory($dir)`, `filenameMaxLength($len)`

**Behavior:** `domainOnly()`, `checkExists(false)`

### Query After Validation

```php
$rule->fileAttributes();    // FileAttributes
$rule->fileSize();          // ?int
$rule->mimeType();          // ?string
$rule->path();              // ?string
$rule->filename();          // ?string
$rule->extension();         // ?string
$rule->getDirectory();      // ?string
$rule->domainType();        // "cdn" | "upload" | "oss" | "oss_internal"
$rule->failedReason();      // failure code
```

## License

MIT
