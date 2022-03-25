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

class AliOSSTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testAssert()
    {
        $this->assertInstanceOf(OssAdapter::class, AliOSS::getClient());
    }
}
