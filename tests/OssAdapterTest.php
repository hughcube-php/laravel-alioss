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

    /**
     * @throws
     * @phpstan-ignore-next-line
     */
    public function caseWithClear(callable $callback)
    {
        $adapter = $this->getOssAdapter();
        $callback($adapter);

        $options = ['delimiter' => '', 'prefix' => 'oss-test/', 'max-keys' => 1000, 'marker' => ''];
        $result = $adapter->getOssClient()->listObjects($adapter->getBucket(), $options);

        $objects = [];
        foreach ($result->getObjectList() as $object) {
            if (in_array($object->getKey(), ['oss-test/', 'oss-test'])) {
                continue;
            }

            $objects[] = $object->getKey();
        }
        if (empty($objects)) {
            return;
        }

        $adapter->deleteObjects($adapter->getBucket(), $objects);
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
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $this->assertFalse($adapter->fileExists($path));
            $adapter->getOssClient()->putObject($adapter->getBucket(), $path, $content);
            $this->assertTrue($adapter->fileExists($path));
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testDirectoryExists()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $path = sprintf('oss-test/%s/%s/', __METHOD__, Str::random(32));

            //$this->assertFalse($adapter->directoryExists($path));
            $adapter->getOssClient()->createObjectDir($adapter->getBucket(), $path);
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
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $adapter->write($path, $content, new Config());

            $this->assertSame($content, $adapter->getOssClient()->getObject($adapter->getBucket(), $path));
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testWriteStream(): void
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, $content);
            rewind($stream);

            $adapter->writeStream($path, $stream, new Config());
            $this->assertSame($content, $adapter->getOssClient()->getObject($adapter->getBucket(), $path));

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
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $adapter->write($path, $content, new Config());
            $this->assertSame($adapter->read($path), $content);
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testReadStream()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $adapter->write($path, $content, new Config());
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
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $adapter->write($path, $content, new Config());
            $this->assertTrue($adapter->fileExists($path));

            $adapter->delete($path);
            $this->assertFalse($adapter->fileExists($path));
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testDeleteDirectory()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

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
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

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
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $adapter->write($path, $content, new Config());
            $this->assertTrue($adapter->fileExists($path));

            foreach ([Visibility::PUBLIC, Visibility::PRIVATE] as $visibility) {
                $adapter->setVisibility($path, $visibility);

                $acl = $adapter->getOssClient()->getObjectAcl($adapter->getBucket(), $path);
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
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $adapter->write($path, $content, new Config());
            $this->assertTrue($adapter->fileExists($path));

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
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $adapter->write($path, $content, new Config());
            $this->assertTrue($adapter->fileExists($path));

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
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $adapter->write($path, $content, new Config());
            $this->assertTrue($adapter->fileExists($path));

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
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $adapter->write($path, $content, new Config());
            $this->assertTrue($adapter->fileExists($path));

            $fileAttributes = $adapter->fileSize($path);
            $this->assertSame($fileAttributes->fileSize(), strlen($content));
        });
    }

    public function testListContents()
    {
        $this->markTestSkipped();
    }

    /**
     * @throws FilesystemException
     * @throws OssException
     */
    public function testMove()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $source = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $adapter->write($source, $content, new Config());
            $this->assertTrue($adapter->fileExists($source));

            $destination = sprintf('oss-test/%s/move/%s.txt', __METHOD__, Str::random(32));
            $adapter->move($source, $destination, new Config());
            $this->assertTrue($adapter->fileExists($destination));

            $this->assertFalse($adapter->fileExists($source));
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
            $source = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $adapter->write($source, $content, new Config());
            $this->assertTrue($adapter->fileExists($source));

            $destination = sprintf('oss-test/%s/copy/%s.txt', __METHOD__, Str::random(32));
            $adapter->copy($source, $destination, new Config());
            $this->assertTrue($adapter->fileExists($destination));
        });
    }

    /**
     * @throws OssException
     */
    public function testUrl()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $url = $adapter->url($path);
            $this->assertTrue(HUrl::isUrlString($url));
        });
    }

    /**
     * @throws OssException
     * @throws FilesystemException
     * @throws GuzzleException
     */
    public function testAuthUrl()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $adapter->write($path, $content, new Config());
            $this->assertTrue($adapter->fileExists($path));

            $url = $adapter->authUrl($path);
            $this->assertTrue(HUrl::isUrlString($url));

            $this->assertSame(
                $this->getHttpClient()->get($url)->getBody()->getContents(),
                $content
            );
        });
    }

    /**
     * @throws OssException
     * @throws FilesystemException
     * @throws GuzzleException
     */
    public function testWriteFromUrl()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $source = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));
            $adapter->write($source, $content, new Config());
            $this->assertTrue($adapter->fileExists($source));

            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));
            $adapter->putUrl($adapter->authUrl($source), $path);
            $this->assertSame($adapter->read($path), $content);
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testPutFile()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $file = tempnam('/tmp', 'aliOssTest_');
            file_put_contents($file, $content, LOCK_EX);

            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));
            $adapter->putFile($file, $path);

            $this->assertTrue($adapter->fileExists($path));
            $this->assertSame($adapter->read($path), $content);
        });
    }

    /**
     * @throws FilesystemException
     */
    public function testDownload()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', __METHOD__, Str::random(32));

            $adapter->write($path, $content, new Config());
            $this->assertTrue($adapter->fileExists($path));

            $file = tempnam('/tmp', 'aliOssTest_');
            $adapter->download($path, $file);

            $this->assertSame(file_get_contents($file), $content);
        });
    }
}
