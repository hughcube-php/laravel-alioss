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
use League\Flysystem\FilesystemException;
use OSS\Core\OssException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

class Meta
{
    public function __construct(protected Request $request)
    {
    }

    /**
     * @throws Exception
     */
    protected function action(): JsonResponse
    {
        $url = HUrl::parse($this->getUrl());
        if (!$url instanceof HUrl) {
            throw new BadRequestHttpException('Url must be a correct URL string!');
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

        $meta = null;
        $ossException = null;
        try {
            $meta = $oss->getObjectMeta($oss->getBucket(), ltrim($url->getPath(), '/'));
        } catch (OssException $ossException) {
        }

        return new JsonResponse([
            'code' => 200,
            'message' => 'ok',
            'data' => [
                'status' => $ossException instanceof OssException ? $ossException->getHTTPStatus() : 200,
                'mimetype' => $meta['content-type'] ?? null,
                'size' => isset($meta['content-length']) ? intval($meta['content-length']) : null,
            ],
        ]);
    }

    protected function getUrl(): mixed
    {
        return $this->request->input('url');
    }

    protected function getClient(): mixed
    {
        return $this->request->get('client');
    }

    protected function hash(mixed $data): string
    {
        return base_convert(abs(crc32(serialize($data))), 10, 36);
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
