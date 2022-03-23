<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 11:45 下午.
 */

namespace HughCube\Laravel\Package\Tests;

use HughCube\Laravel\Package\Driver;
use HughCube\Laravel\Package\Manager;
use HughCube\Laravel\Package\Package;

class FacadeTest extends TestCase
{
    public function testIsFacade()
    {
        $this->assertInstanceOf(Manager::class, Package::getFacadeRoot());
    }

    public function testDriver()
    {
        $this->assertInstanceOf(Driver::class, Package::driver());
    }
}
