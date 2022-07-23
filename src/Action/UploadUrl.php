<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/15
 * Time: 8:42 下午.
 */

namespace HughCube\Laravel\AliOSS\Action;

use Exception;
use HughCube\Laravel\AliOSS\AliOSS;
use HughCube\PUrl\HUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use OSS\Core\OssException;
use OSS\OssClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

class UploadUrl
{
    public function __construct(protected Request $request)
    {
    }

    /**
     * @throws OssException
     * @throws Exception
     */
    protected function action(): JsonResponse
    {
        $method = $this->getMethod() ?: OssClient::OSS_HTTP_PUT;
        if (!is_string($method) || empty(trim($method))) {
            throw new BadRequestHttpException('Method must be a non-empty string!');
        }

        $prefix = $this->getPrefix() ?: 'user';
        if (!is_string($prefix) || empty(trim($prefix))) {
            throw new BadRequestHttpException('Prefix must be a non-empty string!');
        }

        $suffix = $this->getSuffix() ?: null;
        if (false === (is_null($suffix) || (is_string($suffix) && !empty(trim($suffix))))) {
            throw new BadRequestHttpException('The suffix either does not pass, or it must be a non-empty string!');
        }

        $timeout = $this->getTimeout() ?? 60;
        if (!is_numeric($timeout) && empty($timeout)) {
            throw new BadRequestHttpException('Timeout must be a number greater than 0!');
        }

        $client = $this->getClient() ?: null;
        if (!is_null($client) && !is_string($client)) {
            throw new BadRequestHttpException('The client either does not pass, or it must be a non-empty string!');
        }

        try {
            $oss = AliOSS::getClient($client);
        } catch (Throwable) {
            throw new BadRequestHttpException('The client is not recognized!');
        }

        $options = $oss->forbidOverwriteOptions();
        $path = $this->getPath($prefix, $suffix);

        $url = $oss->cdnUrl($path) ?: $oss->url($path);
        $action = $oss->authUploadUrl($path, $timeout, $method, $options);

        return new JsonResponse([
            'code' => 200,
            'message' => 'ok',
            'data' => [
                'url' => $url,
                'path' => HUrl::parse($url)?->getPath(),
                'action' => $action,
                'method' => $method,
                'headers' => [
                    'x-oss-forbid-overwrite' => 'true',
                ],
            ],
        ]);
    }

    /**
     * @throws Exception
     */
    protected function getPath(string $prefix, null|string $suffix): string
    {
        $path = sprintf(
            '%s/%s%s%s/%s%s%s/%s',
            trim($prefix, '/'),
            $this->hash([$_SERVER, $this->request->all(), $this->request->getContent()]),
            $this->hash(microtime()),
            $this->hash([random_int(PHP_INT_MIN, PHP_INT_MAX), Str::random(), random_bytes(100)]),
            $this->hash([random_int(PHP_INT_MIN, PHP_INT_MAX), Str::random(), random_bytes(100)]),
            $this->hash([Auth::id(), Str::random(), Str::random()]),
            $this->hash([random_int(PHP_INT_MIN, PHP_INT_MAX), Str::random(), random_bytes(100)]),
            trim($suffix, '/')
        );

        return trim($path, '/');
    }

    protected function getMethod(): mixed
    {
        return $this->request->get('method');
    }

    protected function getPrefix(): mixed
    {
        return $this->request->get('prefix');
    }

    protected function getSuffix(): mixed
    {
        return $this->request->get('suffix');
    }

    protected function getTimeout(): mixed
    {
        return $this->request->get('timeout');
    }

    protected function getClient(): mixed
    {
        return $this->request->get('client');
    }

    protected function hash(mixed $data): string
    {
        return base_convert(abs(crc32((is_scalar($data) ? $data : serialize($data)))), 10, 36);
    }

    /**
     * @return Response
     * @throws OssException
     *
     */
    public function __invoke(): Response
    {
        return $this->action();
    }
}
