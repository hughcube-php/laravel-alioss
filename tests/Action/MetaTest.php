<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/3/28
 * Time: 15:27.
 */

namespace HughCube\Laravel\AliOSS\Tests\Action;

use GuzzleHttp\Exception\GuzzleException;
use HughCube\Laravel\AliOSS\Action\Meta;
use HughCube\Laravel\AliOSS\AliOSS;
use HughCube\Laravel\AliOSS\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\NoReturn;
use League\Flysystem\FilesystemException;
use OSS\Core\OssException;
use ReflectionException;

/**
 * @group authCase
 */
class MetaTest extends TestCase
{
    /**
     * @throws OssException
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws FilesystemException
     */
    #[NoReturn]
    public function testAction()
    {
        $action = $this->app->make(Meta::class);

        $oss = AliOSS::getClient($this->callMethod($action, 'getClient'));

        $content = Str::random();
        $path = sprintf('/oss-test/%s/%s.txt', md5(__METHOD__), Str::random(32));

        $oss->write($path, $content);
        $this->assertSame($content, $oss->read($path));

        $url = $oss->cdnUrl($path) ?: $oss->url($path);
        $this->assertSame($content, $this->getHttpClient()->get($oss->authUrl($url))->getBody()->getContents());

        $this->setProperty($action, 'request', Request::create(
            '/test',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['url' => $url])
        ));
        $response = $action();

        $this->assertIsNumeric($size = $response->getData(true)['data']['size']);
        $this->assertIsString($mimetype = $response->getData(true)['data']['mimetype']);

        $this->assertEquals(strlen($content), $size);
        $this->assertSame($mimetype, 'text/plain');
    }
}
