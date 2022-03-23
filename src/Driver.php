<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 4:21 下午.
 */

namespace HughCube\Laravel\Package;

class Driver
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @param  array  $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }
}
