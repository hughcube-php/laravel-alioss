<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 11:36 下午.
 */

namespace HughCube\Laravel\AliOSS\Tests;

use Exception;
use HughCube\Laravel\AliOSS\AliOSS;
use HughCube\Laravel\AliOSS\OssAdapter;
use Illuminate\Support\Str;

/**
 * @group authCase
 */
class AliOSSTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testAssert()
    {
        $this->assertInstanceOf(OssAdapter::class, AliOSS::getClient());
    }

    public function testFileExists()
    {
        $this->caseWithClear(function (OssAdapter $adapter) {
            $content = Str::random();
            $path = sprintf('oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

            $this->assertFalse(AliOSS::fileExists($path));
            AliOSS::write($path, $content);
            $this->assertTrue(AliOSS::fileExists($path));
        });
    }
}
