<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/3/28
 * Time: 15:27
 */

namespace HughCube\Laravel\AliOSS\Tests\Action;

use HughCube\Laravel\AliOSS\Action\UploadUrl;
use HughCube\Laravel\AliOSS\Tests\TestCase;
use HughCube\PUrl\HUrl;
use Illuminate\Http\JsonResponse;
use JetBrains\PhpStorm\NoReturn;
use OSS\Core\OssException;

/**
 * @group authCase
 */
class UploadUrlTest extends TestCase
{
    /**
     * @throws OssException
     */
    #[NoReturn]
    public function testAction()
    {
        $action = $this->app->make(UploadUrl::class);
        $response = $action();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertTrue(HUrl::isUrlString($response->getData(true)['data']['url']));
        $this->assertTrue(HUrl::isUrlString($response->getData(true)['data']['action']));
        $this->assertIsArray($response->getData(true)['data']['headers']);
        $this->assertIsString($response->getData(true)['data']['method']);
    }
}
