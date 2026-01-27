<?php

namespace HughCube\Laravel\AliOSS\Tests\Action;

use HughCube\Laravel\AliOSS\Action\UploadUrl;
use HughCube\Laravel\AliOSS\Tests\TestCase;
use Illuminate\Http\Request;
use Mockery;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class UploadUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testConstructor(): void
    {
        $request = new Request();
        $uploadUrl = new UploadUrl($request);

        $this->assertInstanceOf(UploadUrl::class, $uploadUrl);
    }

    public function testGetMethodReturnsRequestValue(): void
    {
        $request = new Request(['method' => 'POST']);
        $uploadUrl = new UploadUrl($request);

        $reflection = new ReflectionClass($uploadUrl);
        $method = $reflection->getMethod('getMethod');

        $this->assertSame('POST', $method->invoke($uploadUrl));
    }

    public function testGetMethodReturnsNullWhenNotSet(): void
    {
        $request = new Request();
        $uploadUrl = new UploadUrl($request);

        $reflection = new ReflectionClass($uploadUrl);
        $method = $reflection->getMethod('getMethod');

        $this->assertNull($method->invoke($uploadUrl));
    }

    public function testGetPrefixReturnsRequestValue(): void
    {
        $request = new Request(['prefix' => 'images']);
        $uploadUrl = new UploadUrl($request);

        $reflection = new ReflectionClass($uploadUrl);
        $method = $reflection->getMethod('getPrefix');

        $this->assertSame('images', $method->invoke($uploadUrl));
    }

    public function testGetSuffixReturnsRequestValue(): void
    {
        $request = new Request(['suffix' => '.jpg']);
        $uploadUrl = new UploadUrl($request);

        $reflection = new ReflectionClass($uploadUrl);
        $method = $reflection->getMethod('getSuffix');

        $this->assertSame('.jpg', $method->invoke($uploadUrl));
    }

    public function testGetTimeoutReturnsRequestValue(): void
    {
        $request = new Request(['timeout' => 120]);
        $uploadUrl = new UploadUrl($request);

        $reflection = new ReflectionClass($uploadUrl);
        $method = $reflection->getMethod('getTimeout');

        $this->assertSame(120, $method->invoke($uploadUrl));
    }

    public function testGetClientReturnsRequestValue(): void
    {
        $request = new Request(['client' => 'custom_oss']);
        $uploadUrl = new UploadUrl($request);

        $reflection = new ReflectionClass($uploadUrl);
        $method = $reflection->getMethod('getClient');

        $this->assertSame('custom_oss', $method->invoke($uploadUrl));
    }

    public function testHashMethod(): void
    {
        $request = new Request();
        $uploadUrl = new UploadUrl($request);

        $reflection = new ReflectionClass($uploadUrl);
        $method = $reflection->getMethod('hash');

        // 相同输入应该产生相同输出
        $hash1 = $method->invoke($uploadUrl, 'test');
        $hash2 = $method->invoke($uploadUrl, 'test');
        $this->assertSame($hash1, $hash2);

        // 不同输入应该产生不同输出
        $hash3 = $method->invoke($uploadUrl, 'different');
        $this->assertNotSame($hash1, $hash3);

        // 数组输入也应该能处理
        $hashArray = $method->invoke($uploadUrl, ['key' => 'value']);
        $this->assertIsString($hashArray);
    }

    public function testHashMethodWithDifferentDataTypes(): void
    {
        $request = new Request();
        $uploadUrl = new UploadUrl($request);

        $reflection = new ReflectionClass($uploadUrl);
        $method = $reflection->getMethod('hash');

        // 字符串
        $this->assertIsString($method->invoke($uploadUrl, 'string'));

        // 整数
        $this->assertIsString($method->invoke($uploadUrl, 12345));

        // 数组
        $this->assertIsString($method->invoke($uploadUrl, [1, 2, 3]));

        // 嵌套数组
        $this->assertIsString($method->invoke($uploadUrl, ['nested' => ['array' => 'value']]));
    }

    public function testGetPathGeneratesUniquePaths(): void
    {
        $request = new Request();
        $uploadUrl = new UploadUrl($request);

        $reflection = new ReflectionClass($uploadUrl);
        $method = $reflection->getMethod('getPath');

        $path1 = $method->invoke($uploadUrl, 'prefix', 'suffix');
        $path2 = $method->invoke($uploadUrl, 'prefix', 'suffix');

        // 由于使用了随机数和时间，两次调用应该产生不同的路径
        $this->assertNotSame($path1, $path2);
    }

    public function testGetPathContainsPrefixAndSuffix(): void
    {
        $request = new Request();
        $uploadUrl = new UploadUrl($request);

        $reflection = new ReflectionClass($uploadUrl);
        $method = $reflection->getMethod('getPath');

        $path = $method->invoke($uploadUrl, 'my-prefix', 'my-suffix.jpg');

        $this->assertStringStartsWith('my-prefix/', $path);
        $this->assertStringEndsWith('my-suffix.jpg', $path);
    }

    public function testGetPathWithNullSuffix(): void
    {
        $request = new Request();
        $uploadUrl = new UploadUrl($request);

        $reflection = new ReflectionClass($uploadUrl);
        $method = $reflection->getMethod('getPath');

        $path = $method->invoke($uploadUrl, 'prefix', null);

        $this->assertStringStartsWith('prefix/', $path);
        $this->assertIsString($path);
    }

    public function testGetPathTrimsSlashes(): void
    {
        $request = new Request();
        $uploadUrl = new UploadUrl($request);

        $reflection = new ReflectionClass($uploadUrl);
        $method = $reflection->getMethod('getPath');

        $path = $method->invoke($uploadUrl, '/prefix/', '/suffix/');

        // 应该不以 / 开头或结尾
        $this->assertStringStartsNotWith('/', $path);
        $this->assertStringEndsNotWith('/', $path);
    }
}
