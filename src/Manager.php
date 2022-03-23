<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 4:19 下午.
 */

namespace HughCube\Laravel\Package;

use Closure;
use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Support\Manager as IlluminateManager;
use InvalidArgumentException;

/**
 * @property callable|ContainerContract|null $container
 * @mixin Driver
 */
class Manager extends IlluminateManager
{
    /**
     * @param  callable|ContainerContract|null  $container
     */
    public function __construct($container = null)
    {
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function extend($driver, Closure $callback)
    {
        return parent::extend($driver, $callback->bindTo($this, $this));
    }

    /**
     * Call a custom driver creator.
     *
     * @param  string  $driver
     * @return mixed
     */
    protected function callCustomCreator($driver)
    {
        return $this->customCreators[$driver]($this, $driver);
    }

    /**
     * @return ContainerContract
     */
    public function getContainer(): ContainerContract
    {
        if (!property_exists($this, 'container') || null === $this->container) {
            return IlluminateContainer::getInstance();
        }

        if (is_callable($this->container)) {
            $this->container = call_user_func($this->container);
        }

        return $this->container;
    }

    /**
     * @return Repository
     *
     * @throws
     * @phpstan-ignore-next-line
     */
    protected function getConfig(): Repository
    {
        if (!property_exists($this, 'config') || null === $this->config) {
            return $this->getContainer()->make('config');
        }

        if (is_callable($this->config)) {
            $this->config = call_user_func($this->config);
        }

        return $this->config;
    }

    /**
     * @param  null|string|int  $name
     * @param  mixed  $default
     * @return array|mixed
     */
    protected function getPackageConfig($name = null, $default = null)
    {
        $key = sprintf('%s.%s', Package::getFacadeAccessor(), $name);
        return $this->getConfig()->get($key, $default);
    }

    /**
     * @inheritdoc
     */
    public function getDefaultDriver(): string
    {
        return $this->getPackageConfig('default', 'default');
    }

    /**
     * @inheritdoc
     */
    protected function createDriver($driver)
    {
        return $this->makeDriver($this->configuration($driver));
    }

    /**
     * Make the Driver instance.
     *
     * @param  array  $config
     * @return Driver
     */
    public function makeDriver(array $config): Driver
    {
        return new Driver($config);
    }

    /**
     * @return array
     */
    protected function getClientDefaultConfig(): array
    {
        return $this->getConfig()->get('defaults', []);
    }

    /**
     * Get the configuration for a client.
     *
     * @param  string  $name
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function configuration(string $name): array
    {
        $name = $name ?: $this->getDefaultDriver();
        $config = $this->getPackageConfig("drivers.$name");

        if (null === $config) {
            throw new InvalidArgumentException("Package client [{$name}] not configured.");
        }

        return array_merge($this->getClientDefaultConfig(), $config);
    }
}
