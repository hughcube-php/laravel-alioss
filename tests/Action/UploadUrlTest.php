<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/3/28
 * Time: 15:27.
 */

namespace HughCube\Laravel\AliOSS\Tests\Action;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use HughCube\Laravel\AliOSS\Action\UploadUrl;
use HughCube\Laravel\AliOSS\AliOSS;
use HughCube\Laravel\AliOSS\OssAdapter;
use HughCube\Laravel\AliOSS\Tests\TestCase;
use HughCube\PUrl\HUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\NoReturn;
use OSS\Core\OssException;
use ReflectionException;

/**
 * @group authCase
 */
class UploadUrlTest extends TestCase
{
    /**
     * @throws OssException
     * @throws GuzzleException
     * @throws ReflectionException
     */
    #[NoReturn]
    public function testAction()
    {
        $action = $this->app->make(UploadUrl::class);

        $oss = AliOSS::getClient($this->callMethod($action, 'getClient'));
        $response = $action();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertTrue(HUrl::isUrlString($url = $response->getData(true)['data']['url']));
        $this->assertTrue(HUrl::isUrlString($action = $response->getData(true)['data']['action']));
        $this->assertIsArray($headers = $response->getData(true)['data']['headers']);
        $this->assertIsString($method = $response->getData(true)['data']['method']);

        /** 不使用返回的Headers  */
        $response = $this->getHttpClient()->request($method, $action, [
            RequestOptions::BODY => ($content = Str::random()),
            RequestOptions::HTTP_ERRORS => false,
        ]);
        $this->assertSame(403, $response->getStatusCode());

        /** 正常上传 */
        $response = $this->getHttpClient()->request($method, $action, [
            RequestOptions::HEADERS => $headers,
            RequestOptions::BODY => ($content = Str::random()),
            RequestOptions::HTTP_ERRORS => false,
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($content, $this->getHttpClient()->get($oss->authUrl($url))->getBody()->getContents());

        /** 不使用返回的Headers  */
        $response = $this->getHttpClient()->request($method, $action, [
            RequestOptions::BODY => ($content = Str::random()),
            RequestOptions::HTTP_ERRORS => false,
        ]);
        $this->assertSame(403, $response->getStatusCode());

        /** 重复上传 */
        $response = $this->getHttpClient()->request($method, $action, [
            RequestOptions::HEADERS => $headers,
            RequestOptions::BODY => ($content = Str::random()),
            RequestOptions::HTTP_ERRORS => false,
        ]);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertNotSame($content, $this->getHttpClient()->get($oss->authUrl($url))->getBody()->getContents());
    }
}
