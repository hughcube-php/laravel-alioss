<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 11:36 下午.
 */

namespace HughCube\Laravel\AliOSS\Tests;

use GuzzleHttp\Exception\GuzzleException;
use HughCube\GuzzleHttp\HttpClientTrait;
use HughCube\Laravel\AliOSS\Acl;
use HughCube\Laravel\AliOSS\OssAdapter;
use HughCube\PUrl\HUrl;
use Illuminate\Support\Str;
use League\Flysystem\Config;
use League\Flysystem\FilesystemException;
use League\Flysystem\Visibility;
use OSS\Core\OssException;
use OSS\OssClient;

/**
 * @group authCase
 */
class OssAdapterTest extends TestCase
{
    use HttpClientTrait;

    protected function makeOssKey($path, OssAdapter $adapter): string
    {
        $key = ltrim(sprintf(
            '%s/%s',
            trim($adapter->getPrefixer()->prefixPath(''), '/'),
            ltrim($path, '/')
        ), '/');

        $this->assertSame($key, ltrim($adapter->makePath($path), '/'));

        return $key;
    }

    public function testGetOssClient()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $this->assertInstanceOf(OssClient::class, $adapter->getOssClient());
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testFileExists()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('/oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $this->assertFalse($adapter->fileExists($path));

            $adapter->getOssClient()->putObject($adapter->getBucket(), $this->makeOssKey($path, $adapter), $content);
            $this->assertTrue($adapter->fileExists($path));
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testDirectoryExists()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $path = sprintf('/oss-test/%s/%s/', md5(__METHOD__), Str::random(32));

            //$this->assertFalse($adapter->directoryExists($path));
            $adapter->getOssClient()->createObjectDir($adapter->getBucket(), $this->makeOssKey($path, $adapter));
            $this->assertTrue($adapter->directoryExists($path));
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testWrite(): void
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('/oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $adapter->write($path, $content, new Config());
            $this->assertSame(
                $content,
                $adapter->getOssClient()->getObject($adapter->getBucket(), $this->makeOssKey($path, $adapter))
            );
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testWriteStream(): void
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, $content);
            rewind($stream);

            $adapter->writeStream($path, $stream, new Config());
            $this->assertSame(
                $content,
                $adapter->getOssClient()->getObject($adapter->getBucket(), $this->makeOssKey($path, $adapter))
            );

            fclose($stream);
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testRead()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $adapter = $this->getOssAdapter();

            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $adapter->write($path, $content, new Config());
            $this->assertSame(
                $content,
                $adapter->getOssClient()->getObject($adapter->getBucket(), $this->makeOssKey($path, $adapter))
            );
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testReadStream()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $adapter->getOssClient()->putObject($adapter->getBucket(), $this->makeOssKey($path, $adapter), $content);
            $stream = $adapter->readStream($path);

            $this->assertTrue(is_resource($stream));
            $this->assertSame($content, stream_get_contents($stream));
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testDelete()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $adapter->getOssClient()->putObject($adapter->getBucket(), $this->makeOssKey($path, $adapter), $content);
            $this->assertTrue($adapter->getOssClient()
                ->doesObjectExist($adapter->getBucket(), $this->makeOssKey($path, $adapter)));

            $adapter->delete($path);
            $this->assertFalse($adapter->getOssClient()
                ->doesObjectExist($adapter->getBucket(), $this->makeOssKey($path, $adapter)));
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testDeleteDirectory()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $adapter->deleteDirectory($path);
            $this->assertTrue(true);
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testCreateDirectory()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $adapter->createDirectory($path, new Config());
            $this->assertTrue(true);
        });
    }

    /**
     * @throws FilesystemException
     * @throws OssException
     */
    public function testSetVisibility()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $key = $this->makeOssKey($path, $adapter);
            $adapter->getOssClient()->putObject($adapter->getBucket(), $key, $content);
            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $key));

            foreach ([Visibility::PUBLIC, Visibility::PRIVATE] as $visibility) {
                $adapter->setVisibility($path, $visibility);
                $acl = $adapter->getOssClient()->getObjectAcl($adapter->getBucket(), $key);
                $this->assertSame(Acl::toAcl($visibility), $acl);
            }
        });
    }

    /**
     * @throws FilesystemException
     * @throws OssException
     */
    public function testVisibility()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $key = $this->makeOssKey($path, $adapter);
            $adapter->getOssClient()->putObject($adapter->getBucket(), $key, $content);
            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $key));

            foreach ([Visibility::PUBLIC, Visibility::PRIVATE] as $visibility) {
                $adapter->setVisibility($path, $visibility);

                $fileAttributes = $adapter->visibility($path);
                $this->assertSame($fileAttributes->visibility(), $visibility);
            }
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testMimeType()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $key = $this->makeOssKey($path, $adapter);
            $adapter->getOssClient()->putObject($adapter->getBucket(), $key, $content);
            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $key));

            $fileAttributes = $adapter->mimeType($path);
            $this->assertSame('text/plain', $fileAttributes->mimeType());
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testLastModified()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $key = $this->makeOssKey($path, $adapter);
            $adapter->getOssClient()->putObject($adapter->getBucket(), $key, $content);
            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $key));

            $fileAttributes = $adapter->lastModified($path);
            $this->assertIsInt($fileAttributes->lastModified());
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testFileSize()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $key = $this->makeOssKey($path, $adapter);
            $adapter->getOssClient()->putObject($adapter->getBucket(), $key, $content);
            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $key));

            $fileAttributes = $adapter->fileSize($path);
            $this->assertSame($fileAttributes->fileSize(), strlen($content));
        });
    }

    public function testListContents()
    {
        $this->assertTrue(true);
    }

    /**
     * @throws FilesystemException
     * @throws OssException
     */
    public function testMove()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $source = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $sourceKey = $this->makeOssKey($source, $adapter);
            $adapter->getOssClient()->putObject($adapter->getBucket(), $sourceKey, $content);
            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $sourceKey));

            $destination = sprintf('oss-test/%s/move/%s.txt', md5(__METHOD__), Str::random(32));
            $adapter->move($source, $destination, new Config());

            $destinationKey = $this->makeOssKey($destination, $adapter);
            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $destinationKey));

            $this->assertFalse($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $sourceKey));
        });
    }

    /**
     * @throws FilesystemException
     * @throws OssException
     */
    public function testCopy()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $source = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $sourceKey = $this->makeOssKey($source, $adapter);
            $adapter->getOssClient()->putObject($adapter->getBucket(), $sourceKey, $content);
            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $sourceKey));

            $destination = sprintf('oss-test/%s/copy/%s.txt', md5(__METHOD__), Str::random(32));
            $adapter->copy($source, $destination, new Config());

            $destinationKey = $this->makeOssKey($destination, $adapter);
            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $destinationKey));

            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $sourceKey));
        });
    }

    /**
     * @throws OssException
     */
    public function testUrl()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $url = HUrl::parse($adapter->url($path));

            $this->assertInstanceOf(HUrl::class, $url);
            $this->assertSame($url->getPath(), sprintf('/%s', $this->makeOssKey($path, $adapter)));
        });
    }

    /**
     * @throws OssException
     * @throws GuzzleException
     */
    public function testAuthUrl()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $key = $this->makeOssKey($path, $adapter);
            $adapter->getOssClient()->putObject($adapter->getBucket(), $key, $content);
            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $key));

            $url = HUrl::parse($adapter->authUrl($path));
            $this->assertInstanceOf(HUrl::class, $url);
            $this->assertSame($url->getPath(), sprintf('/%s', $this->makeOssKey($path, $adapter)));

            $this->assertSame(
                $content,
                $this->getHttpClient()->get($url)->getBody()->getContents()
            );
        });
    }

    /**
     * @throws OssException
     * @throws FilesystemException
     * @throws GuzzleException
     */
    public function testPutUrl()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $source = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $key = $this->makeOssKey($source, $adapter);
            $adapter->getOssClient()->putObject($adapter->getBucket(), $key, $content);
            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $key));

            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));
            $adapter->putUrl($adapter->authUrl($source), $path);

            $this->assertSame($adapter->read($path), $content);
        });
    }

    public function testPutFile()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $file = tempnam('/tmp', 'aliOssTest_');
            file_put_contents($file, $content, LOCK_EX);

            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));
            $adapter->putFile($file, $path);

            $key = $this->makeOssKey($path, $adapter);
            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $key));
            $this->assertSame($adapter->getOssClient()->getObject($adapter->getBucket(), $key), $content);
        });
    }

    public function testDownload()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $key = $this->makeOssKey($path, $adapter);
            $adapter->getOssClient()->putObject($adapter->getBucket(), $key, $content);
            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $key));

            $file = tempnam('/tmp', 'aliOssTest_');
            $adapter->download($path, $file);

            $this->assertSame(file_get_contents($file), $content);
        });
    }

    /**
     * @throws FilesystemException
     * @throws OssException
     * @throws GuzzleException
     */
    public function testPutUrlIfChangeUrl()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $key = $this->makeOssKey($path, $adapter);
            $adapter->getOssClient()->putObject($adapter->getBucket(), $key, $content);
            $this->assertTrue($adapter->getOssClient()->doesObjectExist($adapter->getBucket(), $key));

            /** 通过putUrl的方式上传 */
            $newPath = sprintf('oss-test/%s/1/%s.txt', md5(__METHOD__), Str::random(32));
            $newUrl = $adapter->putUrlAndReturnUrl($adapter->authUrl($path), $newPath);
            $this->assertSame(
                $content,
                $this->getHttpClient()->get($adapter->authUrl($newPath))->getBody()->getContents()
            );

            /** putUrlIfChangeUrl上传, 并且判断路径 */
            $prefix = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));
            $dUrl = $adapter->putUrlIfChangeUrl($adapter->authUrl($newUrl), null, $prefix);
            $this->assertSame(
                ltrim(HUrl::instance($dUrl)->getPath(), '/'),
                $this->makeOssKey(sprintf("/$prefix/%s", $this->makeOssKey($newPath, $adapter)), $adapter)
            );
            $this->assertSame(
                $content,
                $this->getHttpClient()->get($adapter->authUrl($dUrl))->getBody()->getContents()
            );

            /** 改变远程文件的内容 */
            $newContent = Str::random();
            $adapter->write($newPath, $newContent, new Config());
            $this->assertNotSame(
                $content,
                $this->getHttpClient()->get($adapter->authUrl($newPath))->getBody()->getContents()
            );

            /** putUrlIfChangeUrl上传, 并且判断路径, 并且无上传操作 */
            $this->assertSame($dUrl, $adapter->putUrlIfChangeUrl($adapter->authUrl($newUrl), $dUrl, $prefix));
            $this->assertSame(
                $content,
                $this->getHttpClient()->get($adapter->authUrl($dUrl))->getBody()->getContents()
            );
        });
    }
}
