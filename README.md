<h1 align="center"> laravel AliYun oss </h1>

<p>
    <a href="https://github.com/hughcube-php/laravel-alioss/actions?query=workflow%3ATest">
        <img src="https://github.com/hughcube-php/laravel-alioss/workflows/Test/badge.svg" alt="Test Actions status">
    </a>
    <a href="https://github.com/hughcube-php/laravel-alioss/actions?query=workflow%3ALint">
        <img src="https://github.com/hughcube-php/laravel-alioss/workflows/Lint/badge.svg" alt="Lint Actions status">
    </a>
    <a href="https://styleci.io/repos/473234744">
        <img src="https://github.styleci.io/repos/473234744/shield?branch=master" alt="StyleCI">
    </a>
    <a href="https://github.com/hughcube-php/laravel-alioss">
        <img src="https://img.shields.io/badge/php-%3E%3D%207.0-8892BF.svg" alt="PHP Versions Supported">
    </a>
    <a href="https://packagist.org/packages/hughcube/laravel-alioss">
        <img src="https://poser.pugx.org/hughcube-php/laravel-alioss/version" alt="Latest Stable Version">
    </a>
    <a href="https://packagist.org/packages/hughcube/laravel-alioss">
        <img src="https://poser.pugx.org/hughcube-php/laravel-alioss/downloads" alt="Total Downloads">
    </a>
    <a href="https://github.com/hughcube-php/laravel-alioss/blob/master/LICENSE">
        <img src="https://img.shields.io/badge/license-MIT-428f7e.svg" alt="License">
    </a>
    <a href="https://packagist.org/packages/hughcube/laravel-alioss">
        <img src="https://poser.pugx.org/hughcube-php/laravel-alioss/v/unstable" alt="Latest Unstable Version">
    </a>
    <a href="https://packagist.org/packages/hughcube/laravel-alioss">
        <img src="https://poser.pugx.org/hughcube-php/laravel-alioss/composerlock" alt="composer.lock available">
    </a>
</p>

## Installing

```shell
$ composer require hughcube/laravel-alioss -vvv
```

## Add a new disk to your `config/filesystems.php` config:

```php
return [
    'disks' => [
        'alioss' => [
            'driver'          => 'alioss',
            'bucket'          => env('ALIOSS_BUCKET'),
            'uploadBaseUrl'   => env('ALIOSS_UPLOAD_BASE_URL'),
            'cdnBaseUrl'      => env('ALIOSS_CDN_BASE_URL'),
            'prefix'          => env('ALIOSS_PREFIX'),
            'acl'             => env('ALIOSS_ACL'),
            
            'accessKeyId'     => env('ALIOSS_ACCESS_KEY_ID'),
            'accessKeySecret' => env('ALIOSS_ACCESS_KEY_SECRET'),
            'endpoint'        => env('ALIOSS_ENDPOINT'),
            'isCName'         => env('ALIOSS_IS_CNAME'),
            'securityToken'   => env('ALIOSS_SECURITY_TOKEN'),
            'requestProxy'    => env('ALIOSS_REQUEST_PROXY'),
        ],
    ]
];
```

## Usage

TODO

## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/hughcube-php/package/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/hughcube-php/package/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

MIT