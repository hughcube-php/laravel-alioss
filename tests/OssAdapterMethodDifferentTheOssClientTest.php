<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 11:36 下午.
 */

namespace HughCube\Laravel\AliOSS\Tests;

use Exception;
use HughCube\Laravel\AliOSS\OssAdapter;
use OSS\OssClient;
use ReflectionClass;
use ReflectionMethod;

class OssAdapterMethodDifferentTheOssClientTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testAssert()
    {
        foreach ($this->getMethods(OssAdapter::class) as $aMethod) {
            if (in_array($aMethod, ['__construct', '__call'])) {
                continue;
            }

            foreach ($this->getMethods(OssClient::class, ReflectionMethod::IS_PUBLIC) as $cMethod) {
                if ($aMethod === $cMethod) {
                    throw new Exception(sprintf('Cannot mixin OssClient, exists the same method "%s"', $aMethod));
                }
            }
        }

        $this->assertTrue(true);
    }

    public function getMethods($class, $filter = null): array
    {
        $reflection = new ReflectionClass($class);
        $methods = $reflection->getMethods($filter);

        return array_map(function (ReflectionMethod $method) {
            return $method->getName();
        }, $methods);
    }
}
