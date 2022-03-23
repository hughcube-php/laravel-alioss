<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 11:45 下午.
 */

namespace HughCube\Laravel\Package\Tests;

class ManagerTest extends TestCase
{
    /**
     * @dataProvider configProvider
     */
    public function testStore($config)
    {
        $this->assertTrue(true);
    }

    /**
     * @return array[]
     */
    public function configProvider(): array
    {
        return [
            [],
        ];
    }
}
