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
use HughCube\PUrl\Url;
use Illuminate\Http\JsonResponse;
use OSS\Core\OssException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

trait Meta
{
    /**
     * @throws Exception
     */
    protected function action(): JsonResponse
    {
        $url = $this->getUrl();
        $client = $this->getClient();

        $meta = null;
        $ossException = null;

        try {
            $meta = $client->getObjectMeta($client->getBucket(), ltrim($url->getPath(), '/'));
        } catch (OssException $ossException) {
        }

        return new JsonResponse([
            'code' => 200,
            'message' => 'ok',
            'data' => [
                'mimetype' => $meta['content-type'] ?? null,
                'size' => isset($meta['content-length']) ? intval($meta['content-length']) : null,
                'status' => $ossException instanceof OssException ? $ossException->getHTTPStatus() : 200,
            ],
        ]);
    }

    protected function getUrl(): Url
    {
        $uri = $this->getRequest()->input('url');

        $url = Url::parse($uri);
        if (!$url instanceof Url) {
            throw new BadRequestHttpException('Url must be a correct URL string!');
        }

        return $url;
    }

    protected function getClient(): OssAdapter
    {
        $name = $this->getRequest()->get('client');

        if (!is_null($name) && !is_string($name)) {
            throw new BadRequestHttpException('The client either does not pass, or it must be a non-empty string!');
        }

        try {
            return AliOSS::getClient($name);
        } catch (Throwable) {
            throw new BadRequestHttpException('The client is not recognized!');
        }
    }

    abstract protected function getRequest();
}
