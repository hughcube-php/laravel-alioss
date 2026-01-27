<?php

/**
 * PHPUnit Bootstrap
 * 配置 SSL 证书以解决 Windows 环境下的证书问题
 */

$caFile = __DIR__ . '/cacert.pem';

if (file_exists($caFile)) {
    // 设置环境变量
    putenv('SSL_CERT_FILE=' . $caFile);
    putenv('CURL_CA_BUNDLE=' . $caFile);

    // 设置 PHP ini
    ini_set('curl.cainfo', $caFile);
    ini_set('openssl.cafile', $caFile);
}

// 加载 Composer 自动加载
require dirname(__DIR__) . '/vendor/autoload.php';
