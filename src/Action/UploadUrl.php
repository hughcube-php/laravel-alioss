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
use HughCube\Laravel\AliOSS\OssAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use OSS\Core\OssException;
use OSS\OssClient;
use Symfony\Component\HttpFoundation\Response;

class UploadUrl
{
    public function __construct(protected Request $request)
    {
    }

    /**
     * @throws OssException
     * @throws Exception
     */
    public function action(): JsonResponse
    {
        $oss = $this->getOss();
        $path = $this->getPath();

        $url = $oss->cdnUrl($path) ?: $oss->url($path);

        $options = $oss->forbidOverwriteOptions();
        $method = OssClient::OSS_HTTP_PUT;
        $action = $oss->authUploadUrl($path, ($this->getTimeout() ?: 60), OssClient::OSS_HTTP_PUT, $options);

        return new JsonResponse([
            'code'    => 200,
            'message' => 'ok',
            'data'    => [
                'url'     => $url,
                'action'  => $action,
                'method'  => $method,
                'headers' => [
                    'x-oss-forbid-overwrite' => 'true',
                ],
            ],
        ]);
    }

    /**
     * @throws Exception
     */
    protected function getPath(): string
    {
        $path = sprintf(
            '%s/%s%s%s/%s%s%s/%s',
            $this->getPrefix(),
            $this->hash([microtime(), Str::random()]),
            $this->hash([$_SERVER, $this->request->all(), $this->request->getContent()]),
            $this->hash([random_int(PHP_INT_MIN, PHP_INT_MAX), Str::random(), random_bytes(100)]),
            $this->hash([random_int(PHP_INT_MIN, PHP_INT_MAX), Str::random(), random_bytes(100)]),
            $this->hash([random_int(PHP_INT_MIN, PHP_INT_MAX), Str::random(), random_bytes(100)]),
            $this->hash([Auth::id(), Str::random(), Str::random()]),
            $this->getPrefix()
        );

        return trim($path, '/');
    }

    protected function getPrefix(): null|string
    {
        return $this->request->get('prefix') ?: null;
    }

    protected function getSuffix(): null|string
    {
        return $this->request->get('suffix') ?: null;
    }

    protected function getTimeout(): int|null
    {
        return $this->request->get('timeout') ?: null;
    }

    protected function getOss(): OssAdapter
    {
        return AliOSS::getClient($this->request->get('oss'));
    }

    protected function hash(mixed $data): string
    {
        return base_convert(abs(crc32(serialize($data))), 10, 36);
    }

    /**
     * @throws OssException
     *
     * @return Response
     */
    public function __invoke(): Response
    {
        return $this->action();
    }
}
