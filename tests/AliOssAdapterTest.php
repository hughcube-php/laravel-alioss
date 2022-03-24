<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 11:36 下午.
 */

namespace HughCube\Laravel\AliOSS\Tests;

use Exception;
use HughCube\Laravel\AliOSS\Acl;
use HughCube\Laravel\AliOSS\OssAdapter;
use Illuminate\Support\Str;
use League\Flysystem\Config;
use League\Flysystem\FilesystemException;
use League\Flysystem\Visibility;
use OSS\Core\OssException;
use OSS\OssClient;

class AliOssAdapterTest extends TestCase
{
    /**
     * @dataProvider makeAliOssAdapter
     */
    public function testGetOssClient(OssAdapter $adapter)
    {
        $this->assertInstanceOf(OssClient::class, $adapter->getOssClient());
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws  FilesystemException
     */
    public function testFileExists(OssAdapter $adapter)
    {
        $content = Str::random();
        $path = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $this->assertFalse($adapter->fileExists($path));
        $adapter->getOssClient()->putObject($adapter->getBucket(), $path, $content);
        $this->assertTrue($adapter->fileExists($path));
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     */
    public function testDirectoryExists(OssAdapter $adapter)
    {
        $path = sprintf("oss-test/%s/%s/", __METHOD__, Str::random(32));

        #$this->assertFalse($adapter->directoryExists($path));
        $adapter->getOssClient()->createObjectDir($adapter->getBucket(), $path);
        $this->assertTrue($adapter->directoryExists($path));
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     */
    public function testWrite(OssAdapter $adapter): void
    {
        $content = Str::random();
        $path = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $adapter->write($path, $content, new Config());

        $this->assertSame($content, $adapter->getOssClient()->getObject($adapter->getBucket(), $path));
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     */
    public function testWriteStream(OssAdapter $adapter): void
    {
        $content = Str::random();
        $path = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $content);
        rewind($stream);

        $adapter->writeStream($path, $stream, new Config());
        $this->assertSame($content, $adapter->getOssClient()->getObject($adapter->getBucket(), $path));

        fclose($stream);
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     */
    public function testRead(OssAdapter $adapter)
    {
        $content = Str::random();
        $path = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $adapter->write($path, $content, new Config());
        $this->assertSame($adapter->read($path), $content);
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     */
    public function testReadStream(OssAdapter $adapter)
    {
        $content = Str::random();
        $path = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $adapter->write($path, $content, new Config());
        $stream = $adapter->readStream($path);

        $this->assertTrue(is_resource($stream));
        $this->assertSame($content, stream_get_contents($stream));
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     */
    public function testDelete(OssAdapter $adapter)
    {
        $content = Str::random();
        $path = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $adapter->write($path, $content, new Config());
        $this->assertTrue($adapter->fileExists($path));

        $adapter->delete($path);
        $this->assertFalse($adapter->fileExists($path));
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     */
    public function testDeleteDirectory(OssAdapter $adapter)
    {
        $path = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $adapter->deleteDirectory($path);
        $this->assertTrue(true);
    }


    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     */
    public function testCreateDirectory(OssAdapter $adapter)
    {
        $path = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $adapter->createDirectory($path, new Config());
        $this->assertTrue(true);
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     * @throws OssException
     */
    public function testSetVisibility(OssAdapter $adapter)
    {
        $content = Str::random();
        $path = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $adapter->write($path, $content, new Config());
        $this->assertTrue($adapter->fileExists($path));

        foreach ([Visibility::PUBLIC, Visibility::PRIVATE] as $visibility) {
            $adapter->setVisibility($path, $visibility);

            $acl = $adapter->getOssClient()->getObjectAcl($adapter->getBucket(), $path);
            $this->assertSame(Acl::toAcl($visibility), $acl);
        }
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     * @throws OssException
     */
    public function testVisibility(OssAdapter $adapter)
    {
        $content = Str::random();
        $path = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $adapter->write($path, $content, new Config());
        $this->assertTrue($adapter->fileExists($path));

        foreach ([Visibility::PUBLIC, Visibility::PRIVATE] as $visibility) {
            $adapter->setVisibility($path, $visibility);

            $fileAttributes = $adapter->visibility($path);
            $this->assertSame($fileAttributes->visibility(), $visibility);
        }
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     */
    public function testMimeType(OssAdapter $adapter)
    {
        $content = Str::random();
        $path = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $adapter->write($path, $content, new Config());
        $this->assertTrue($adapter->fileExists($path));

        $fileAttributes = $adapter->mimeType($path);
        $this->assertSame('text/plain', $fileAttributes->mimeType());
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     */
    public function testLastModified(OssAdapter $adapter)
    {
        $content = Str::random();
        $path = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $adapter->write($path, $content, new Config());
        $this->assertTrue($adapter->fileExists($path));

        $fileAttributes = $adapter->lastModified($path);
        $this->assertIsInt($fileAttributes->lastModified());
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     */
    public function testFileSize(OssAdapter $adapter)
    {
        $content = Str::random();
        $path = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $adapter->write($path, $content, new Config());
        $this->assertTrue($adapter->fileExists($path));

        $fileAttributes = $adapter->fileSize($path);
        $this->assertSame($fileAttributes->fileSize(), strlen($content));
    }

    /**
     * @dataProvider makeAliOssAdapter
     */
    public function testListContents(OssAdapter $adapter): iterable
    {
        $this->markTestSkipped();
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     * @throws OssException
     */
    public function testMove(OssAdapter $adapter)
    {
        $content = Str::random();
        $source = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $adapter->write($source, $content, new Config());
        $this->assertTrue($adapter->fileExists($source));

        $destination = sprintf("oss-test/%s/move/%s.txt", __METHOD__, Str::random(32));
        $adapter->move($source, $destination, new Config());
        $this->assertTrue($adapter->fileExists($destination));

        $this->assertFalse($adapter->fileExists($source));
    }

    /**
     * @dataProvider makeAliOssAdapter
     * @throws FilesystemException
     * @throws OssException
     */
    public function testCopy(OssAdapter $adapter)
    {
        $content = Str::random();
        $source = sprintf("oss-test/%s/%s.txt", __METHOD__, Str::random(32));

        $adapter->write($source, $content, new Config());
        $this->assertTrue($adapter->fileExists($source));

        $destination = sprintf("oss-test/%s/copy/%s.txt", __METHOD__, Str::random(32));
        $adapter->copy($source, $destination, new Config());
        $this->assertTrue($adapter->fileExists($destination));
    }

    /**
     * @throws Exception
     */
    public function makeAliOssAdapter(): array
    {
        return [
            [
                new OssAdapter([
                    'driver' => 'alioss',
                    'accessKeyId' => env('ALIOSS_ACCESS_KEY_ID'),
                    'accessKeySecret' => env('ALIOSS_ACCESS_KEY_SECRET'),
                    'endpoint' => env('ALIOSS_ENDPOINT'),
                    'bucket' => env('ALIOSS_BUCKET'),
                    'isCName' => env('ALIOSS_IS_CNAME'),
                    'securityToken' => env('ALIOSS_SECURITY_TOKEN'),
                    'requestProxy' => env('ALIOSS_REQUEST_PROXY'),
                ])
            ]
        ];
    }
}
