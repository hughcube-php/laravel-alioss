<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/18
 * Time: 10:31 下午.
 */

namespace HughCube\Laravel\Package;

use Illuminate\Support\Facades\Facade as IlluminateFacade;
use Illuminate\Support\Str;

/**
 * Class Package.
 *
 * @method static Driver driver(string $name = null)
 *
 * @see \HughCube\Laravel\Package\Manager
 * @see \HughCube\Laravel\Package\ServiceProvider
 */
class Package extends IlluminateFacade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    public static function getFacadeAccessor(): string
    {
        return lcfirst(Str::afterLast(static::class, "\\"));
    }
}
